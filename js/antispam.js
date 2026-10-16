/* Copyright (C) 2026 THERSANE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Anti-spam tab of an email account:
 * - on/off switch saved in ajax, the switch flips at once and comes back on error. Everything the script needs (url,
 *   token, error message) is in data attributes of the switch;
 * - edit form: the login and password rows are shown only for the "own login and password" connection mode.
 */
(function () {
	'use strict';

	function showSwitch(el, value) {
		el.dataset.value = value ? '1' : '0';
		el.querySelector('.crmcc-antispam-switch-on').classList.toggle('hidden', !value);
		el.querySelector('.crmcc-antispam-switch-off').classList.toggle('hidden', !!value);
	}

	document.addEventListener('change', function (event) {
		var select = event.target.closest('.crmcc-antispam-authmode');
		if (!select) {
			return;
		}
		document.querySelectorAll('.crmcc-antispam-login-row').forEach(function (row) {
			row.classList.toggle('hidden', select.value !== 'login');
		});
	});

	document.addEventListener('click', function (event) {
		var el = event.target.closest('.crmcc-antispam-switch');
		if (!el || el.dataset.busy === '1') {
			return;
		}
		event.preventDefault();

		var previous = el.dataset.value === '1';
		var next = !previous;
		showSwitch(el, next);
		el.dataset.busy = '1';

		var body = new URLSearchParams();
		body.append('action', 'setactive');
		body.append('id', el.dataset.id);
		body.append('value', next ? '1' : '0');
		body.append('token', el.dataset.token);

		fetch(el.dataset.url, {method: 'POST', body: body, credentials: 'same-origin'})
			.then(function (response) {
				return response.json().catch(function () {
					return {success: false};
				});
			})
			.then(function (data) {
				if (!data || !data.success) {
					throw new Error(data && data.error ? data.error : '');
				}
			})
			.catch(function (error) {
				showSwitch(el, previous);
				var message = el.dataset.error + (error && error.message ? ' ' + error.message : '');
				if (typeof $ !== 'undefined' && $.jnotify) {
					$.jnotify(message, 'error', true);
				} else {
					console.error(message);
				}
			})
			.finally(function () {
				el.dataset.busy = '0';
			});
	});
})();
