<template>
	<div class="xfiles-settings">
		<h3>{{ t('xfiles', 'Vault settings') }}</h3>

		<!-- Auto-lock timeout -->
		<div class="xfiles-settings__section">
			<label for="xfiles-timeout">{{ t('xfiles', 'Auto-lock timeout') }}</label>
			<select
				id="xfiles-timeout"
				v-model="autoLockSeconds"
				:disabled="savingSettings"
				@change="onSaveSettings">
				<option :value="60">{{ t('xfiles', '1 minute') }}</option>
				<option :value="300">{{ t('xfiles', '5 minutes') }}</option>
				<option :value="900">{{ t('xfiles', '15 minutes') }}</option>
				<option :value="1800">{{ t('xfiles', '30 minutes') }}</option>
				<option :value="3600">{{ t('xfiles', '1 hour') }}</option>
				<option :value="0">{{ t('xfiles', 'Never') }}</option>
			</select>
		</div>

		<!-- Max file size -->
		<div class="xfiles-settings__section">
			<label for="xfiles-maxsize">{{ t('xfiles', 'Maximum file size (MB)') }}</label>
			<input
				id="xfiles-maxsize"
				v-model.number="maxFileSizeMb"
				type="number"
				min="1"
				max="500"
				:disabled="savingSettings"
				@change="onSaveSettings">
		</div>

		<hr>

		<!-- Change password -->
		<h3>{{ t('xfiles', 'Change vault password') }}</h3>
		<form class="xfiles-settings__form" @submit.prevent="onChangePassword">
			<NcPasswordField
				:value.sync="currentPassword"
				:label="t('xfiles', 'Current password')"
				:disabled="changingPassword"
				required />
			<NcPasswordField
				:value.sync="newPassword"
				:label="t('xfiles', 'New password')"
				:placeholder="t('xfiles', 'Minimum 4 characters')"
				:disabled="changingPassword"
				required />
			<NcPasswordField
				:value.sync="confirmPassword"
				:label="t('xfiles', 'Confirm new password')"
				:disabled="changingPassword"
				:error="confirmError"
				required />
			<NcButton
				type="primary"
				native-type="submit"
				:disabled="!canChangePassword || changingPassword"
				:wide="true">
				<template #icon>
					<NcLoadingIcon v-if="changingPassword" :size="20" />
				</template>
				{{ t('xfiles', 'Change password') }}
			</NcButton>
		</form>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { changeVaultPassword, updateVaultSettings } from '../services/api.js'

export default {
	name: 'SettingsView',
	components: {
		NcButton,
		NcLoadingIcon,
		NcPasswordField,
	},
	props: {
		initialAutoLockSeconds: {
			type: Number,
			default: 300,
		},
		initialMaxFileSizeMb: {
			type: Number,
			default: 50,
		},
	},
	data() {
		return {
			autoLockSeconds: this.initialAutoLockSeconds,
			maxFileSizeMb: this.initialMaxFileSizeMb,
			savingSettings: false,
			currentPassword: '',
			newPassword: '',
			confirmPassword: '',
			changingPassword: false,
		}
	},
	computed: {
		confirmError() {
			if (this.confirmPassword && this.newPassword !== this.confirmPassword) {
				return t('xfiles', 'Passwords do not match')
			}
			return ''
		},
		canChangePassword() {
			return this.currentPassword.length >= 4
				&& this.newPassword.length >= 4
				&& this.newPassword === this.confirmPassword
		},
	},
	methods: {
		t,
		async onSaveSettings() {
			this.savingSettings = true
			try {
				await updateVaultSettings(this.autoLockSeconds, this.maxFileSizeMb)
				showSuccess(t('xfiles', 'Settings saved'))
			} catch (e) {
				showError(t('xfiles', 'Failed to save settings'))
			} finally {
				this.savingSettings = false
			}
		},
		async onChangePassword() {
			if (!this.canChangePassword) return
			this.changingPassword = true
			try {
				await changeVaultPassword(this.currentPassword, this.newPassword)
				showSuccess(t('xfiles', 'Password changed. Vault will lock now.'))
				this.currentPassword = ''
				this.newPassword = ''
				this.confirmPassword = ''
				this.$emit('password-changed')
			} catch (e) {
				if (e.response?.status === 403) {
					showError(t('xfiles', 'Current password is incorrect'))
				} else {
					showError(t('xfiles', 'Failed to change password'))
				}
			} finally {
				this.changingPassword = false
			}
		},
	},
}
</script>

<style scoped>
.xfiles-settings {
	padding: 16px 20px;
	max-width: 400px;
}

.xfiles-settings h3 {
	margin-top: 0;
	margin-bottom: 12px;
}

.xfiles-settings__section {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin-bottom: 16px;
}

.xfiles-settings__section label {
	font-weight: 500;
	font-size: 0.9em;
}

.xfiles-settings__section select,
.xfiles-settings__section input[type="number"] {
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.xfiles-settings hr {
	margin: 20px 0;
	border: none;
	border-top: 1px solid var(--color-border);
}

.xfiles-settings__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
</style>
