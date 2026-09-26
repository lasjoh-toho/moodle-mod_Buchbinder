<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_buchbinder\local;

use stdClass;

/**
 * Queue for imports that run as ad-hoc task (cron), so that large PDFs and scan
 * batches do not hit web server time limits.
 *
 * Uploaded files are kept in the file area "jobfile" (itemid = job id) until the
 * job has been processed.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_queue {
    /** @var string Waiting for cron. */
    const STATUS_QUEUED = 'queued';
    /** @var string Being processed. */
    const STATUS_RUNNING = 'running';
    /** @var string Finished. */
    const STATUS_DONE = 'done';
    /** @var string Finished with errors only. */
    const STATUS_FAILED = 'failed';

    /** @var int Finished jobs are shown for this long. */
    const KEEP_FINISHED = DAYSECS;

    /**
     * Whether imports run in the background.
     *
     * @return bool
     */
    public static function is_background(): bool {
        $value = get_config('buchbinder', 'backgroundimport');
        return $value === false || (bool)$value;
    }

    /**
     * Queue files for import.
     *
     * @param document $document
     * @param \stored_file[] $files
     * @param array $ops cleanup steps
     * @param array $meta source metadata (title, author, url)
     * @return stdClass job record
     */
    public static function enqueue(document $document, array $files, array $ops, array $meta): stdClass {
        global $DB;
        $now = time();
        $job = (object)[
            'buchbinderid' => $document->get_instance()->id,
            'status' => self::STATUS_QUEUED,
            'options' => json_encode(['ops' => array_values($ops), 'meta' => $meta]),
            'filenames' => implode(', ', array_map(fn($f) => $f->get_filename(), $files)),
            'pagecount' => 0,
            'message' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $job->id = $DB->insert_record('buchbinder_job', $job);

        $fs = get_file_storage();
        foreach ($files as $file) {
            $fs->create_file_from_storedfile([
                'contextid' => $document->get_context()->id,
                'component' => 'mod_buchbinder',
                'filearea' => 'jobfile',
                'itemid' => $job->id,
                'filepath' => '/',
            ], $file);
        }

        if (self::is_background()) {
            $task = new \mod_buchbinder\task\import_job();
            $task->set_custom_data(['jobid' => $job->id]);
            \core\task\manager::queue_adhoc_task($task);
        } else {
            self::process($job->id);
            $job = $DB->get_record('buchbinder_job', ['id' => $job->id]);
        }
        return $job;
    }

    /**
     * Process a job. Errors of single files are collected, the job never throws.
     *
     * @param int $jobid
     */
    public static function process(int $jobid): void {
        global $DB;
        $job = $DB->get_record('buchbinder_job', ['id' => $jobid]);
        if (!$job || $job->status === self::STATUS_DONE || $job->status === self::STATUS_FAILED) {
            return;
        }
        $cm = get_coursemodule_from_instance('buchbinder', $job->buchbinderid);
        $fs = get_file_storage();
        if (!$cm) {
            // The activity has been deleted in the meantime.
            $DB->delete_records('buchbinder_job', ['id' => $job->id]);
            return;
        }
        $document = document::from_cmid($cm->id);
        $contextid = $document->get_context()->id;
        $DB->update_record('buchbinder_job', (object)['id' => $job->id, 'status' => self::STATUS_RUNNING,
            'timemodified' => time()]);

        $options = json_decode($job->options ?? '', true) ?: [];
        $importer = new importer($document, $options['ops'] ?? []);
        $pages = 0;
        $errors = [];
        foreach ($fs->get_area_files($contextid, 'mod_buchbinder', 'jobfile', $job->id, 'id', false) as $file) {
            try {
                $pages += $importer->import_file($file, $options['meta'] ?? []);
            } catch (\Throwable $e) {
                $errors[] = $file->get_filename() . ': ' . $e->getMessage();
            }
        }
        $fs->delete_area_files($contextid, 'mod_buchbinder', 'jobfile', $job->id);
        $DB->update_record('buchbinder_job', (object)[
            'id' => $job->id,
            'status' => ($errors && !$pages) ? self::STATUS_FAILED : self::STATUS_DONE,
            'pagecount' => $pages,
            'message' => implode("\n", $errors),
            'timemodified' => time(),
        ]);
    }

    /**
     * Jobs to show in the studio: all unfinished ones and recently finished ones.
     *
     * @param document $document
     * @return stdClass[]
     */
    public static function get_jobs(document $document): array {
        global $DB;
        return $DB->get_records_select(
            'buchbinder_job',
            'buchbinderid = :id AND (status IN (:q, :r) OR timemodified > :since)',
            ['id' => $document->get_instance()->id, 'q' => self::STATUS_QUEUED, 'r' => self::STATUS_RUNNING,
            'since' => time() - self::KEEP_FINISHED],
            'timecreated DESC',
            '*',
            0,
            20
        );
    }

    /**
     * Remove a finished job from the list.
     *
     * @param document $document
     * @param int $jobid
     */
    public static function dismiss(document $document, int $jobid): void {
        global $DB;
        $DB->delete_records_select(
            'buchbinder_job',
            'id = ? AND buchbinderid = ? AND status IN (?, ?)',
            [$jobid, $document->get_instance()->id, self::STATUS_DONE, self::STATUS_FAILED]
        );
    }

    /**
     * Template data of the job list.
     *
     * @param document $document
     * @param \moodle_url $dismissurl
     * @return array
     */
    public static function export(document $document, \moodle_url $dismissurl): array {
        $jobs = [];
        $pending = false;
        $stale = false;
        foreach (self::get_jobs($document) as $job) {
            $open = in_array($job->status, [self::STATUS_QUEUED, self::STATUS_RUNNING]);
            $pending = $pending || $open;
            $stale = $stale || ($job->status === self::STATUS_QUEUED && $job->timecreated < time() - 10 * MINSECS);
            $jobs[] = [
                'id' => $job->id,
                'filenames' => $job->filenames,
                'status' => get_string('jobstatus_' . $job->status, 'mod_buchbinder'),
                'open' => $open,
                'failed' => $job->status === self::STATUS_FAILED,
                'pagecount' => $job->pagecount,
                'message' => $job->message,
                'time' => userdate($job->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'dismissurl' => $open ? '' : (new \moodle_url($dismissurl, ['jobid' => $job->id]))->out(false),
            ];
        }
        return ['jobs' => $jobs, 'hasjobs' => !empty($jobs), 'pending' => $pending, 'stale' => $stale];
    }

    /**
     * Delete all jobs of a document.
     *
     * @param document $document
     */
    public static function delete_all(document $document): void {
        global $DB;
        $DB->delete_records('buchbinder_job', ['buchbinderid' => $document->get_instance()->id]);
    }
}
