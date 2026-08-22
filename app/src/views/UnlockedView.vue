<template>
	<div class="xfiles-unlocked">
		<div class="xfiles-unlocked__header">
			<h2>{{ t('xfiles', 'X-Files') }}</h2>
			<div class="xfiles-unlocked__actions">
				<NcButton
					type="secondary"
					:aria-label="t('xfiles', 'Upload image')"
					@click="triggerUpload">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('xfiles', 'Upload') }}
				</NcButton>
				<NcButton
					type="tertiary"
					:aria-label="t('xfiles', 'Lock vault')"
					@click="onLock">
					<template #icon>
						<LockIcon :size="20" />
					</template>
				</NcButton>
			</div>
		</div>

		<!-- Loading state -->
		<div v-if="loading" class="xfiles-unlocked__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<!-- Empty state -->
		<NcEmptyContent
			v-else-if="images.length === 0"
			:name="t('xfiles', 'Your vault is empty')"
			:description="t('xfiles', 'Upload photos to protect them in your vault.')">
			<template #icon>
				<ImageIcon :size="64" />
			</template>
			<template #action>
				<NcButton type="primary" @click="triggerUpload">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('xfiles', 'Upload your first image') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Image grid -->
		<div v-else class="xfiles-unlocked__grid">
			<div
				v-for="image in images"
				:key="image.id"
				class="xfiles-unlocked__tile"
				tabindex="0"
				role="button"
				:aria-label="image.original_name"
				@click="openViewer(image)"
				@keydown.enter="openViewer(image)">
				<img
					:src="getThumbnailUrl(image.id)"
					:alt="image.original_name"
					class="xfiles-unlocked__thumb"
					loading="lazy">
				<div class="xfiles-unlocked__tile-overlay">
					<NcButton
						type="tertiary"
						class="xfiles-unlocked__delete-btn"
						:aria-label="t('xfiles', 'Delete')"
						@click.stop="onDelete(image)">
						<template #icon>
							<DeleteIcon :size="18" />
						</template>
					</NcButton>
				</div>
			</div>
		</div>

		<!-- Image viewer modal -->
		<NcModal
			v-if="viewerImage"
			:name="viewerImage.original_name"
			size="large"
			@close="viewerImage = null">
			<div class="xfiles-viewer">
				<img
					:src="getImageUrl(viewerImage.id)"
					:alt="viewerImage.original_name"
					class="xfiles-viewer__img">
				<div class="xfiles-viewer__info">
					<span>{{ viewerImage.original_name }}</span>
					<span>{{ formatSize(viewerImage.size) }}</span>
					<span v-if="viewerImage.width">{{ viewerImage.width }} × {{ viewerImage.height }}</span>
				</div>
			</div>
		</NcModal>

		<!-- Hidden file input -->
		<input
			ref="fileInput"
			type="file"
			accept="image/*"
			multiple
			class="xfiles-unlocked__file-input"
			@change="onFileSelected">
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import ImageIcon from 'vue-material-design-icons/Image.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { listImages, uploadImage, deleteImage, lockVault, getImageUrl, getThumbnailUrl } from '../services/api.js'

export default {
	name: 'UnlockedView',
	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcModal,
		DeleteIcon,
		ImageIcon,
		LockIcon,
		PlusIcon,
	},
	data() {
		return {
			images: [],
			total: 0,
			loading: true,
			uploading: false,
			viewerImage: null,
		}
	},
	async mounted() {
		await this.fetchImages()
	},
	methods: {
		t,
		getImageUrl,
		getThumbnailUrl,
		async fetchImages() {
			this.loading = true
			try {
				const data = await listImages()
				this.images = data.images
				this.total = data.total
			} catch (e) {
				if (e.response?.status === 403) {
					// Vault locked (session expired)
					this.$emit('locked')
					return
				}
				showError(t('xfiles', 'Failed to load images'))
			} finally {
				this.loading = false
			}
		},
		triggerUpload() {
			this.$refs.fileInput.click()
		},
		async onFileSelected(event) {
			const files = Array.from(event.target.files)
			if (files.length === 0) return

			this.uploading = true
			let successCount = 0

			for (const file of files) {
				try {
					await uploadImage(file)
					successCount++
				} catch (e) {
					const msg = e.response?.data?.ocs?.data?.error || file.name
					showError(t('xfiles', 'Failed to upload: {name}', { name: msg }))
				}
			}

			// Reset input
			this.$refs.fileInput.value = ''
			this.uploading = false

			if (successCount > 0) {
				showSuccess(t('xfiles', '{count} image(s) uploaded', { count: successCount }))
				await this.fetchImages()
			}
		},
		openViewer(image) {
			this.viewerImage = image
		},
		async onDelete(image) {
			if (!confirm(t('xfiles', 'Delete "{name}" permanently?', { name: image.original_name }))) {
				return
			}

			try {
				await deleteImage(image.id)
				this.images = this.images.filter(i => i.id !== image.id)
				this.total--
				showSuccess(t('xfiles', 'Image deleted'))
			} catch (e) {
				showError(t('xfiles', 'Failed to delete image'))
			}
		},
		async onLock() {
			await lockVault()
			this.$emit('locked')
		},
		formatSize(bytes) {
			if (bytes < 1024) return bytes + ' B'
			if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
			return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
		},
	},
}
</script>

<style scoped>
.xfiles-unlocked {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.xfiles-unlocked__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 16px;
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
}

.xfiles-unlocked__header h2 {
	margin: 0;
	font-size: 1.2em;
}

.xfiles-unlocked__actions {
	display: flex;
	gap: 4px;
	align-items: center;
}

.xfiles-unlocked__loading {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
}

.xfiles-unlocked__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: 4px;
	padding: 8px;
	overflow-y: auto;
	flex: 1;
}

.xfiles-unlocked__tile {
	position: relative;
	aspect-ratio: 1;
	overflow: hidden;
	border-radius: var(--border-radius);
	cursor: pointer;
	background: var(--color-background-dark);
}

.xfiles-unlocked__thumb {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.xfiles-unlocked__tile-overlay {
	position: absolute;
	top: 0;
	right: 0;
	padding: 4px;
	opacity: 0;
	transition: opacity 0.15s;
}

.xfiles-unlocked__tile:hover .xfiles-unlocked__tile-overlay {
	opacity: 1;
}

.xfiles-unlocked__tile:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.xfiles-unlocked__tile:focus .xfiles-unlocked__tile-overlay {
	opacity: 1;
}

.xfiles-unlocked__delete-btn {
	background: rgba(0, 0, 0, 0.5) !important;
	color: white !important;
	border-radius: 50%;
}

.xfiles-unlocked__file-input {
	display: none;
}

.xfiles-viewer {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 16px;
	max-height: 80vh;
}

.xfiles-viewer__img {
	max-width: 100%;
	max-height: 70vh;
	object-fit: contain;
	border-radius: var(--border-radius);
}

.xfiles-viewer__info {
	display: flex;
	gap: 16px;
	margin-top: 12px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
</style>
