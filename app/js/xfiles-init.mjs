/**
 * X-Files — FileAction registration for NC 34 Files app.
 *
 * This is an ESM module (.mjs) loaded by Nextcloud as type="module".
 * It accesses window._nc_files_scope.v4_0 — the shared registry used
 * by @nextcloud/files 4.x in NC 34 — to register the "Classify" action.
 *
 * NC 34 determines script type by file extension:
 *   .mjs → type="module" (shares scopedGlobals via _nc_files_scope)
 *   .js  → defer (isolated, cannot share registry)
 *
 * This file is NOT bundled by webpack. It's a standalone ESM module.
 */

const IMAGE_MIMES = [
	'image/jpeg',
	'image/png',
	'image/gif',
	'image/webp',
	'image/heic',
	'image/heif',
	'image/bmp',
	'image/tiff',
];

const LOCK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 17a2 2 0 0 1-2-2c0-1.11.89-2 2-2a2 2 0 0 1 2 2 2 2 0 0 1-2 2m6 3V10H6v10h12m0-12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10c0-1.11.89-2 2-2h1V6a5 5 0 0 1 5-5 5 5 0 0 1 5 5v2h1m-6-5a3 3 0 0 0-3 3v2h6V6a3 3 0 0 0-3-3Z"/></svg>';

/**
 * Get the NC Files scope (shared registry).
 */
function getScope() {
	window._nc_files_scope ??= {};
	window._nc_files_scope.v4_0 ??= {};
	return window._nc_files_scope.v4_0;
}

/**
 * Register a file action in the NC 34 shared registry.
 * Mirrors @nextcloud/files 4.x registerFileAction() behavior.
 */
function registerAction(action) {
	const scope = getScope();
	scope.fileActions ??= new Map();

	if (scope.fileActions.has(action.id)) {
		console.warn(`[X-Files] Action ${action.id} already registered`);
		return;
	}

	scope.fileActions.set(action.id, action);
	console.info(`[X-Files] Classify action registered in NC Files scope`);
}

/**
 * Generate a URL (equivalent to @nextcloud/router generateUrl).
 */
function generateUrl(path) {
	const root = document.querySelector('head')?.getAttribute('data-requesttoken')
		? '' : '';
	// Use OC.generateUrl if available, otherwise construct manually
	if (window.OC?.generateUrl) {
		return window.OC.generateUrl(path);
	}
	return path;
}

/**
 * Translate a string (equivalent to @nextcloud/l10n t()).
 */
function t(app, text, vars) {
	if (window.OC?.L10N?.translate) {
		return window.OC.L10N.translate(app, text, vars);
	}
	if (window.t) {
		return window.t(app, text, vars);
	}
	// Fallback: simple variable replacement
	if (vars) {
		return Object.entries(vars).reduce((s, [k, v]) => s.replace(`{${k}}`, v), text);
	}
	return text;
}

/**
 * Show toast notifications.
 */
function showSuccess(msg) {
	if (window.OCP?.Toast?.success) {
		window.OCP.Toast.success(msg);
	} else if (window.OC?.Notification?.showTemporary) {
		window.OC.Notification.showTemporary(msg);
	}
}

function showError(msg) {
	if (window.OCP?.Toast?.error) {
		window.OCP.Toast.error(msg);
	} else if (window.OC?.Notification?.showTemporary) {
		window.OC.Notification.showTemporary(msg, { type: 'error' });
	}
}

function showWarning(msg) {
	if (window.OCP?.Toast?.warning) {
		window.OCP.Toast.warning(msg);
	} else if (window.OC?.Notification?.showTemporary) {
		window.OC.Notification.showTemporary(msg);
	}
}

/**
 * Import a single file to the vault.
 */
async function classifyFile(node) {
	const url = generateUrl('/apps/xfiles/api/v1/images/import');
	const payload = {};

	if (node.fileid) {
		payload.fileId = node.fileid;
	} else if (node.path) {
		payload.path = node.path;
	} else {
		return { success: false, error: 'No file identifier' };
	}

	try {
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'OCS-APIRequest': 'true',
				'requesttoken': window.OC?.requestToken || document.querySelector('head[data-requesttoken]')?.getAttribute('data-requesttoken') || '',
			},
			body: JSON.stringify(payload),
		});

		if (response.ok) {
			const data = await response.json();
			if (data?.success) {
				return { success: true };
			}
			return { success: false, error: data?.error || 'Unknown error' };
		}

		// HTTP error
		const status = response.status;
		let errorMsg;
		try {
			const errData = await response.json();
			errorMsg = errData?.error;
		} catch (e) {
			// Could not parse JSON
		}

		if (status === 400) return { success: false, error: errorMsg || 'Invalid file' };
		if (status === 403) return { success: false, error: 'Access denied' };
		if (status === 404) return { success: false, error: errorMsg || 'Vault not found' };
		if (status === 413) return { success: false, error: 'File too large' };
		if (status >= 500) return { success: false, error: 'Server error' };

		return { success: false, error: `HTTP ${status}` };
	} catch (e) {
		return { success: false, error: 'Connection failed' };
	}
}

// Register the Classify action
registerAction({
	id: 'xfiles-classify',

	displayName({ nodes }) {
		return nodes.length > 1
			? t('xfiles', 'Classify these files')
			: t('xfiles', 'Classify');
	},

	iconSvgInline() {
		return LOCK_SVG;
	},

	enabled({ nodes }) {
		if (!nodes || nodes.length === 0) return false;
		return nodes.every(node => node.mime && IMAGE_MIMES.includes(node.mime));
	},

	async exec({ nodes }) {
		const node = nodes[0];
		if (!node) return false;

		const result = await classifyFile(node);
		if (result.success) {
			showSuccess(t('xfiles', '"{name}" classified', { name: node.basename || node.displayname || 'file' }));
			return true;
		}

		showError(t('xfiles', 'Classify failed: {error}', { error: result.error }));
		return false;
	},

	async execBatch({ nodes }) {
		if (!nodes || nodes.length === 0) return [];

		const results = [];
		const errors = [];

		for (const node of nodes) {
			const result = await classifyFile(node);
			results.push(result.success);
			if (!result.success) {
				errors.push({ name: node.basename || 'file', error: result.error });
			}
		}

		const successCount = results.filter(Boolean).length;
		const failCount = nodes.length - successCount;

		if (successCount > 0 && failCount === 0) {
			showSuccess(t('xfiles', '{count} file(s) classified', { count: String(successCount) }));
		} else if (successCount > 0) {
			showWarning(t('xfiles', '{success} classified, {fail} failed', {
				success: String(successCount),
				fail: String(failCount),
			}));
		} else {
			showError(t('xfiles', 'All files failed to classify'));
		}

		for (const err of errors) {
			console.warn(`[X-Files] Failed: "${err.name}": ${err.error}`);
		}

		return results.map(s => s ? true : false);
	},

	order: 50,
});
