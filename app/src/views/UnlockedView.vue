<template>
	<div class="xfiles-unlocked">
		<div class="xfiles-unlocked__header">
			<h2>{{ t('xfiles', 'X-Files') }}</h2>
			<NcButton
				type="tertiary"
				:aria-label="t('xfiles', 'Lock vault')"
				@click="onLock">
				<template #icon>
					<LockIcon :size="20" />
				</template>
				{{ t('xfiles', 'Lock') }}
			</NcButton>
		</div>
		<NcEmptyContent
			:name="t('xfiles', 'Your vault is empty')"
			:description="t('xfiles', 'Upload photos to protect them in your vault.')">
			<template #icon>
				<ImageIcon :size="64" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import ImageIcon from 'vue-material-design-icons/Image.vue'
import { translate as t } from '@nextcloud/l10n'
import { lockVault } from '../services/api.js'

export default {
	name: 'UnlockedView',
	components: {
		NcButton,
		NcEmptyContent,
		LockIcon,
		ImageIcon,
	},
	methods: {
		t,
		async onLock() {
			await lockVault()
			this.$emit('locked')
		},
	},
}
</script>

<style scoped>
.xfiles-unlocked__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 16px;
	border-bottom: 1px solid var(--color-border);
}

.xfiles-unlocked__header h2 {
	margin: 0;
	font-size: 1.2em;
}
</style>
