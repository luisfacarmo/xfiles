/**
 * X-Files — FileAction registration for Files app.
 * Loaded via LoadAdditionalScriptsEvent when the Files app loads.
 *
 * Registers "Classify" action on image files.
 * Uses the NC 34 real runtime contract: callbacks receive
 * { nodes, view, folder, contents } — NOT (files, view).
 */

import { FileAction, registerFileAction } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError, showWarning } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

console.info('[X-Files] init.js loaded — registering Classify action')

const lockSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 17a2 2 0 0 1-2-2c0-1.11.89-2 2-2a2 2 0 0 1 2 2 2 2 0 0 1-2 2m6 3V10H6v10h12m0-12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10c0-1.11.89-2 2-2h1V6a5 5 0 0 1 5-5 5 5 0 0 1 5 5v2h1m-6-5a3 3 0 0 0-3 3v2h6V6a3 3 0 0 0-3-3Z"/></svg>'

const imageMimes = [
	'image/jpeg',
	'image/png',
	'image/gif',
	'image/webp',
	'image/heic',
	'image/heif',
	'image/bmp',
	'image/tiff',
]

/**
 * Import a single file to the vault. Returns { success, error? }.
 */
async function classifyFile(node) {
	const url = generateUrl('/apps/xfiles/api/v1/images/import')
	const payload = {}

	// Prefer fileId over path (more robust)
	if (node.fileid) {
		payload.fileId = node.fileid
	} else if (node.path) {
		payload.path = node.path
	} else {
		return { success: false, error: 'No file identifier available' }
	}

	try {
		const response = await axios.post(url, payload)

		if (response.data?.success) {
			return { success: true }
		}

		// Server returned 200 but success=false
		return {
			success: false,
			error: response.data?.error || t('xfiles', 'Unknown error'),
		}
	} catch (e) {
		const status = e.response?.status
		const serverError = e.response?.data?.error

		if (status === 400) {
			return { success: false, error: serverError || t('xfiles', 'Invalid file') }
		}
		if (status === 403) {
			return { success: false, error: t('xfiles', 'Access denied') }
		}
		if (status === 404) {
			return { success: false, error: serverError || t('xfiles', 'Vault not found — set up X-Files first') }
		}
		if (status === 409) {
			return { success: false, error: t('xfiles', 'File already exists in vault') }
		}
		if (status === 413) {
			return { success: false, error: t('xfiles', 'File is too large') }
		}
		if (status === 422) {
			return { success: false, error: serverError || t('xfiles', 'File cannot be processed') }
		}
		if (status >= 500) {
			return { success: false, error: t('xfiles', 'Server error — please try again') }
		}

		// Network error or unexpected
		return { success: false, error: t('xfiles', 'Connection failed') }
	}
}

const classifyAction = new FileAction({
	id: 'xfiles-classify',

	displayName({ nodes }) {
		return nodes.length > 1
			? t('xfiles', 'Classify these files')
			: t('xfiles', 'Classify')
	},

	iconSvgInline() {
		return lockSvg
	},

	enabled({ nodes }) {
		console.debug('[X-Files] enabled() called, nodes:', nodes?.length, 'first mime:', nodes?.[0]?.mime)
		if (!nodes || nodes.length === 0) {
			return false
		}
		const result = nodes.every(
			(node) => node.mime && imageMimes.includes(node.mime),
		)
		console.debug('[X-Files] enabled() result:', result)
		return result
	},

	async exec({ nodes }) {
		console.debug('[X-Files] exec() called, nodes:', nodes?.length)
		const node = nodes[0]
		if (!node) {
			showError(t('xfiles', 'No file selected'))
			return false
		}

		const result = await classifyFile(node)

		if (result.success) {
			showSuccess(t('xfiles', '"{name}" classified', { name: node.basename }))
			return true
		}

		showError(t('xfiles', 'Failed to classify "{name}": {error}', {
			name: node.basename,
			error: result.error,
		}))
		return false
	},

	async execBatch({ nodes }) {
		if (!nodes || nodes.length === 0) {
			showError(t('xfiles', 'No files selected'))
			return []
		}

		const results = []
		const errors = []

		for (const node of nodes) {
			const result = await classifyFile(node)
			results.push(result.success)
			if (!result.success) {
				errors.push({ name: node.basename, error: result.error })
			}
		}

		const successCount = results.filter(Boolean).length
		const failCount = nodes.length - successCount

		if (successCount > 0 && failCount === 0) {
			showSuccess(t('xfiles', '{count} file(s) classified', { count: successCount }))
		} else if (successCount > 0 && failCount > 0) {
			showWarning(t('xfiles', '{success} classified, {fail} failed', {
				success: successCount,
				fail: failCount,
			}))
		} else {
			showError(t('xfiles', 'All files failed to classify'))
		}

		// Log individual failures
		for (const err of errors) {
			console.warn(`[X-Files] Failed to classify "${err.name}": ${err.error}`)
		}

		return results.map(s => s ? true : false)
	},

	order: 50,
})

registerFileAction(classifyAction)
console.info('[X-Files] Classify action registered. Total actions:', window._nc_fileactions?.length)
