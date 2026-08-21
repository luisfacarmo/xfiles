import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const baseUrl = generateOcsUrl('apps/xfiles/api/v1')

export async function getVaultStatus() {
	const response = await axios.get(`${baseUrl}/vault/status`)
	return response.data.ocs.data
}

export async function setupVault(password) {
	const response = await axios.post(`${baseUrl}/vault/setup`, { password })
	return response.data.ocs.data
}

export async function unlockVault(password) {
	const response = await axios.post(`${baseUrl}/vault/unlock`, { password })
	return response.data.ocs.data
}

export async function lockVault() {
	const response = await axios.post(`${baseUrl}/vault/lock`)
	return response.data.ocs.data
}

export async function recoverVault(recoveryKey, newPassword) {
	const response = await axios.post(`${baseUrl}/vault/recover`, {
		recovery_key: recoveryKey,
		new_password: newPassword,
	})
	return response.data.ocs.data
}
