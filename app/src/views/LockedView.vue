<template>
	<div class="xfiles-locked">
		<NcEmptyContent
			:name="t('xfiles', 'Vault locked')"
			:description="t('xfiles', 'Enter your vault password to access your protected photos.')">
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
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import LockOpenIcon from 'vue-material-design-icons/LockOpen.vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { unlockVault } from '../services/api.js'

export default {
	name: 'LockedView',
	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcPasswordField,
		LockIcon,
		LockOpenIcon,
	},
	data() {
		return {
			password: '',
			loading: false,
			error: '',
		}
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
	},
}
</script>

<style scoped>
.xfiles-locked__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	width: 300px;
	margin-top: 16px;
}
</style>
