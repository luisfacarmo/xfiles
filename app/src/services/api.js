import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

const baseUrl = generateOcsUrl('apps/xfiles/api/v1')
const appBaseUrl = generateUrl('/apps/xfiles')

// Vault lifecycle
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

export async function changeVaultPassword(currentPassword, newPassword) {
	const response = await axios.post(`${baseUrl}/vault/password`, {
		current_password: currentPassword,
		new_password: newPassword,
	})
	return response.data.ocs.data
}

export async function updateVaultSettings(autoLockSeconds, maxFileSizeMb) {
	const response = await axios.post(`${baseUrl}/vault/settings`, {
		auto_lock_seconds: autoLockSeconds,
		max_file_size_mb: maxFileSizeMb,
	})
	return response.data.ocs.data
}

// Images
export async function listImages(limit = 100, offset = 0) {
	const response = await axios.get(`${appBaseUrl}/api/v1/images`, {
		params: { limit, offset },
	})
	return response.data
}

export async function uploadImage(file) {
	const formData = new FormData()
	formData.append('file', file)
	const response = await axios.post(`${appBaseUrl}/api/v1/images/upload`, formData, {
		headers: { 'Content-Type': 'multipart/form-data' },
	})
	return response.data
}

export async function deleteImage(id) {
	const response = await axios.delete(`${appBaseUrl}/api/v1/images/${id}`)
	return response.data
}

export function getImageUrl(id) {
	return `${appBaseUrl}/api/v1/images/${id}/download`
}

export function getThumbnailUrl(id) {
	return `${appBaseUrl}/api/v1/images/${id}/thumb`
}
