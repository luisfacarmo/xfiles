<template>
	<NcContent app-name="xfiles">
		<NcAppContent>
			<NcEmptyContent
				:name="t('xfiles', 'X-Files')"
				:description="t('xfiles', 'Trust no one — your photo vault is being prepared.')">
				<template #icon>
					<LockIcon :size="64" />
				</template>
			</NcEmptyContent>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import LockIcon from 'vue-material-design-icons/Lock.vue'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppContent,
		NcEmptyContent,
		LockIcon,
	},
}
</script>
