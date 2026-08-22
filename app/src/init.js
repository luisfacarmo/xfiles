/**
 * X-Files — FileAction registration for Files app context menu.
 * This script is loaded via LoadAdditionalScriptsEvent when the Files app loads.
 * It registers a "Send to X-Files" action on image files.
 */

import { FileAction, registerFileAction } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

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

const sendToVaultAction = new FileAction({
	id: 'xfiles-send-to-vault',
	displayName: () => t('xfiles', 'Send to X-Files'),
	iconSvgInline: () => lockSvg,

	enabled(files) {
		// Show only for image files
		return files.length > 0 && files.every(
			(file) => file.mime && imageMimes.includes(file.mime),
		)
	},

	async exec(file) {
		try {
			const url = generateUrl('/apps/xfiles/api/v1/images/import')
			const response = await axios.post(url, {
				path: file.path,
			})

			if (response.data?.success) {
				showSuccess(t('xfiles', '"{name}" sent to X-Files vault', { name: file.basename }))
				return true
			}
			showError(response.data?.error || t('xfiles', 'Failed to send to vault'))
			return false
		} catch (e) {
			if (e.response?.status === 403) {
				showError(t('xfiles', 'Vault is locked. Open X-Files and unlock first.'))
			} else if (e.response?.status === 404) {
				showError(t('xfiles', 'No vault found. Set up X-Files first.'))
			} else {
				showError(t('xfiles', 'Failed to send to vault'))
			}
			return false
		}
	},

	async execBatch(files) {
		const results = []
		for (const file of files) {
			results.push(await this.exec(file, null, ''))
		}
		return results
	},

	order: 50,
})

registerFileAction(sendToVaultAction)
