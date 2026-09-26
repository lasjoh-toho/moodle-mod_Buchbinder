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

/**
 * Polls the status of background imports and reloads the studio when they are finished.
 *
 * @module     mod_buchbinder/jobs
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    var INTERVAL = 5000;

    var poll = function(cmid) {
        Ajax.call([{methodname: 'mod_buchbinder_get_import_jobs', args: {cmid: cmid}}])[0].then(function(jobs) {
            var open = jobs.some(function(job) {
                return job.status === 'queued' || job.status === 'running';
            });
            if (open) {
                setTimeout(poll.bind(null, cmid), INTERVAL);
            } else {
                window.location.reload();
            }
            return null;
        }).catch(function() {
            // Try again later, e.g. after a short network problem.
            setTimeout(poll.bind(null, cmid), INTERVAL * 4);
        });
    };

    return {
        /**
         * Start polling.
         *
         * @param {number} cmid
         */
        init: function(cmid) {
            setTimeout(poll.bind(null, cmid), INTERVAL);
        }
    };
});
