<template>
	<NcContent app-name="xfiles">
		<NcAppContent>
			<div v-if="loading" class="xfiles-loading">
				<NcLoadingIcon :size="44" />
			</div>
			<SetupView
				v-else-if="state === 'not_setup'"
				@setup-complete="onSetupComplete" />
			<LockedView
				v-else-if="state === 'locked'"
				@unlocked="onUnlocked" />
			<UnlockedView
				v-else-if="state === 'unlocked'"
				@locked="onLocked" />
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import SetupView from './views/SetupView.vue'
import LockedView from './views/LockedView.vue'
import UnlockedView from './views/UnlockedView.vue'
import { getVaultStatus } from './services/api.js'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppContent,
		NcLoadingIcon,
		SetupView,
		LockedView,
		UnlockedView,
	},
	data() {
		return {
			state: 'locked', // not_setup | locked | unlocked
			loading: true,
		}
	},
	async mounted() {
		await this.fetchStatus()
	},
	methods: {
		async fetchStatus() {
			this.loading = true
			try {
				const data = await getVaultStatus()
				this.state = data.status
			} catch (e) {
				// If we can't get status, show locked as safe default
				this.state = 'locked'
			} finally {
				this.loading = false
			}
		},
		onSetupComplete() {
			this.state = 'unlocked'
		},
		onUnlocked() {
			this.state = 'unlocked'
		},
		onLocked() {
			this.state = 'locked'
		},
	},
}
</script>

<style scoped>
.xfiles-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	min-height: 300px;
}
</style>
