<template>
	<div class="xfiles-locked">
		<NcEmptyContent
			:name="t('xfiles', 'The truth is out there')"
			:description="t('xfiles', 'Enter your vault password to access your classified files.')">
			<template #icon>
				<LockIcon :size="64" />
			</template>
			<template #action>
				<form class="xfiles-locked__form" @submit.prevent="onSubmit">
					<NcPasswordField
						ref="passwordInput"
						:value.sync="password"
						:label="t('xfiles', 'Vault password')"
						:disabled="loading"
						:error="error"
						required
						@update:value="error = ''" />
					<NcButton
						type="primary"
						native-type="submit"
						:disabled="!password || loading"
						:wide="true">
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
							<LockOpenIcon v-else :size="20" />
						</template>
						{{ t('xfiles', 'Unlock') }}
					</NcButton>
				</form>
			</template>
		</NcEmptyContent>

		<!-- Recovery link — subtle, bottom-right -->
		<button
			class="xfiles-locked__recovery"
			:title="t('xfiles', 'Recover vault with recovery key')"
			@click="showRecovery = true">
			<KeyIcon :size="18" />
		</button>

		<!-- Recovery Dialog -->
		<NcDialog
			v-if="showRecovery"
			:name="t('xfiles', 'Recover access')"
			@closing="showRecovery = false">
			<p class="xfiles-locked__recovery-desc">
				{{ t('xfiles', 'Enter your recovery key and set a new vault password.') }}
			</p>
			<form class="xfiles-locked__form" @submit.prevent="onRecover">
				<NcTextField
					:value.sync="recoveryKey"
					:label="t('xfiles', 'Recovery key')"
					:placeholder="t('xfiles', 'XFLS-XXXX-XXXX-XXXX-XXXX-XXXX')"
					:disabled="recoveryLoading" />
				<NcPasswordField
					:value.sync="newPassword"
					:label="t('xfiles', 'New vault password')"
					:placeholder="t('xfiles', 'Minimum 4 characters')"
					:disabled="recoveryLoading" />
				<NcButton
					type="primary"
					native-type="submit"
					:disabled="!canRecover || recoveryLoading"
					:wide="true">
					<template #icon>
						<NcLoadingIcon v-if="recoveryLoading" :size="20" />
					</template>
					{{ t('xfiles', 'Reset password') }}
				</NcButton>
			</form>
		</NcDialog>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import KeyIcon from 'vue-material-design-icons/KeyVariant.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import LockOpenIcon from 'vue-material-design-icons/LockOpen.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { unlockVault, recoverVault } from '../services/api.js'

export default {
	name: 'LockedView',
	components: {
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		NcPasswordField,
		NcTextField,
		KeyIcon,
		LockIcon,
		LockOpenIcon,
	},
	data() {
		return {
			password: '',
			loading: false,
			error: '',
			showRecovery: false,
			recoveryKey: '',
			newPassword: '',
			recoveryLoading: false,
		}
	},
	computed: {
		canRecover() {
			return this.recoveryKey.length >= 24 && this.newPassword.length >= 4
		},
	},
	mounted() {
		this.$nextTick(() => {
			this.$refs.passwordInput?.$el?.querySelector('input')?.focus()
		})
	},
	methods: {
		t,
		async onSubmit() {
			if (!this.password) return
			this.loading = true
			this.error = ''
			try {
				await unlockVault(this.password)
				this.$emit('unlocked')
			} catch (e) {
				if (e.response?.status === 403) {
					this.error = t('xfiles', 'Invalid password')
				} else if (e.response?.status === 429) {
					this.error = t('xfiles', 'Too many attempts. Please wait.')
				} else {
					showError(t('xfiles', 'Failed to unlock vault'))
				}
				this.password = ''
			} finally {
				this.loading = false
			}
		},
		async onRecover() {
			if (!this.canRecover) return
			this.recoveryLoading = true
			try {
				await recoverVault(this.recoveryKey, this.newPassword)
				showSuccess(t('xfiles', 'Password reset successfully. You can now unlock with your new password.'))
				this.showRecovery = false
				this.recoveryKey = ''
				this.newPassword = ''
			} catch (e) {
				if (e.response?.status === 403) {
					showError(t('xfiles', 'Invalid recovery key'))
				} else {
					showError(t('xfiles', 'Failed to reset password'))
				}
			} finally {
				this.recoveryLoading = false
			}
		},
	},
}
</script>

<style scoped>
.xfiles-locked {
	position: relative;
	height: 100%;
}

.xfiles-locked__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	width: 300px;
	margin-top: 16px;
}

.xfiles-locked__recovery {
	position: absolute;
	bottom: 16px;
	right: 16px;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	padding: 0;
	border: none;
	border-radius: 50%;
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	opacity: 0.5;
	transition: opacity 0.2s, color 0.2s;
}

.xfiles-locked__recovery:hover {
	opacity: 1;
	color: var(--color-main-text);
	background: var(--color-background-hover);
}

.xfiles-locked__recovery-desc {
	margin-bottom: 16px;
	color: var(--color-text-maxcontrast);
}
</style>
