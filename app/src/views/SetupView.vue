<template>
	<div class="xfiles-setup">
		<NcEmptyContent
			:name="t('xfiles', 'Set up your vault')"
			:description="t('xfiles', 'Create a password to protect your most sensitive photos.')">
			<template #icon>
				<LockPlusIcon :size="64" />
			</template>
			<template #action>
				<form class="xfiles-setup__form" @submit.prevent="onSubmit">
					<NcPasswordField
						:value.sync="password"
						:label="t('xfiles', 'Vault password')"
						:placeholder="t('xfiles', 'Minimum 4 characters')"
						:disabled="loading"
						required />
					<NcPasswordField
						:value.sync="passwordConfirm"
						:label="t('xfiles', 'Confirm password')"
						:placeholder="t('xfiles', 'Repeat your password')"
						:disabled="loading"
						:error="confirmError"
						required />
					<NcButton
						type="primary"
						native-type="submit"
						:disabled="!canSubmit || loading"
						:wide="true">
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
						</template>
						{{ t('xfiles', 'Create Vault') }}
					</NcButton>
				</form>
			</template>
		</NcEmptyContent>

		<!-- Recovery Key Dialog -->
		<NcDialog
			v-if="recoveryKey"
			:name="t('xfiles', 'Save your recovery key')"
			:can-close="false">
			<p class="xfiles-setup__recovery-warning">
				{{ t('xfiles', 'This key is the only way to recover your vault if you forget your password. Save it in a safe place. It will not be shown again.') }}
			</p>
			<code class="xfiles-setup__recovery-key">{{ recoveryKey }}</code>
			<template #actions>
				<NcButton type="primary" @click="onRecoveryKeySaved">
					{{ t('xfiles', 'I have saved my recovery key') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import LockPlusIcon from 'vue-material-design-icons/LockPlus.vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { setupVault } from '../services/api.js'

export default {
	name: 'SetupView',
	components: {
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		NcPasswordField,
		LockPlusIcon,
	},
	data() {
		return {
			password: '',
			passwordConfirm: '',
			loading: false,
			recoveryKey: null,
		}
	},
	computed: {
		confirmError() {
			if (this.passwordConfirm && this.password !== this.passwordConfirm) {
				return t('xfiles', 'Passwords do not match')
			}
			return ''
		},
		canSubmit() {
			return this.password.length >= 4
				&& this.password === this.passwordConfirm
		},
	},
	methods: {
		t,
		async onSubmit() {
			if (!this.canSubmit) return
			this.loading = true
			try {
				const result = await setupVault(this.password)
				this.recoveryKey = result.recovery_key
			} catch (e) {
				const msg = e.response?.data?.ocs?.data?.error || t('xfiles', 'Failed to create vault')
				showError(msg)
			} finally {
				this.loading = false
			}
		},
		onRecoveryKeySaved() {
			this.recoveryKey = null
			this.$emit('setup-complete')
		},
	},
}
</script>

<style scoped>
.xfiles-setup__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	width: 300px;
	margin-top: 16px;
}

.xfiles-setup__recovery-warning {
	margin-bottom: 12px;
	color: var(--color-warning);
}

.xfiles-setup__recovery-key {
	display: block;
	padding: 16px;
	margin: 12px 0;
	font-size: 1.2em;
	font-weight: bold;
	text-align: center;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	user-select: all;
}
</style>
