<template>
	<div
		class="xfiles-unlocked"
		@dragover.prevent="onDragOver"
		@dragleave.prevent="onDragLeave"
		@drop.prevent="onDrop">
		<!-- Header -->
		<div class="xfiles-unlocked__header">
			<div class="xfiles-unlocked__header-left">
				<h2>{{ t('xfiles', 'X-Files') }} <small v-if="total > 0" style="font-weight:normal;opacity:0.6">{{ total }}</small></h2>
				<span v-if="autoLockSeconds > 0 && remainingSeconds > 0" class="xfiles-unlocked__countdown">
					{{ formatCountdown(remainingSeconds) }}
				</span>
			</div>
			<div class="xfiles-unlocked__actions">
				<NcButton
					v-if="selectMode && selected.length > 0"
					type="error"
					@click="onBatchDelete">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('xfiles', 'Delete ({count})', { count: selected.length }) }}
				</NcButton>
				<NcButton
					type="tertiary"
					:aria-label="t('xfiles', 'Select multiple')"
					:class="{ 'xfiles-unlocked__select-active': selectMode }"
					@click="toggleSelectMode">
					<template #icon>
						<CheckboxMultipleIcon :size="20" />
					</template>
				</NcButton>
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
					:aria-label="t('xfiles', 'Settings')"
					@click="showSettings = !showSettings">
					<template #icon>
						<CogIcon :size="20" />
					</template>
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

		<!-- Upload progress bar -->
		<div v-if="uploading" class="xfiles-unlocked__progress">
			<div class="xfiles-unlocked__progress-bar" :style="{ width: uploadProgress + '%' }" />
			<span class="xfiles-unlocked__progress-text">
				{{ t('xfiles', 'Uploading {current}/{total}...', { current: uploadCurrent, total: uploadTotal }) }}
			</span>
		</div>

		<!-- Drag and drop overlay -->
		<div v-if="dragging" class="xfiles-unlocked__dropzone">
			<ImageIcon :size="48" />
			<p>{{ t('xfiles', 'Drop images here to add to vault') }}</p>
		</div>

		<!-- Loading state -->
		<div v-if="loading" class="xfiles-unlocked__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<!-- Empty state -->
		<NcEmptyContent
			v-else-if="images.length === 0 && !dragging"
			:name="t('xfiles', 'Your vault is empty')"
			:description="t('xfiles', 'Upload or drag photos to protect them in your vault.')">
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
		<div v-else-if="!dragging" class="xfiles-unlocked__grid">
			<div
				v-for="image in images"
				:key="image.id"
				class="xfiles-unlocked__tile"
				:class="{ 'xfiles-unlocked__tile--selected': isSelected(image.id) }"
				tabindex="0"
				role="button"
				:aria-label="image.original_name"
				@click="onTileClick(image, $event)"
				@keydown.enter="onTileClick(image, $event)">
				<img
					:src="getThumbnailUrl(image.id)"
					:alt="image.original_name"
					class="xfiles-unlocked__thumb"
					loading="lazy">
				<!-- Select checkbox -->
				<div v-if="selectMode" class="xfiles-unlocked__tile-check">
					<CheckboxIcon v-if="isSelected(image.id)" :size="22" />
					<CheckboxBlankIcon v-else :size="22" />
				</div>
				<!-- Delete overlay (only in non-select mode) -->
				<div v-if="!selectMode" class="xfiles-unlocked__tile-overlay">
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
			<div class="xfiles-viewer" @keydown="onViewerKeydown" tabindex="0">
				<!-- Prev button -->
				<button
					v-if="hasPrev"
					class="xfiles-viewer__nav xfiles-viewer__nav--prev"
					:aria-label="t('xfiles', 'Previous image')"
					@click="viewerPrev">
					<ChevronLeftIcon :size="36" />
				</button>

				<img
					:src="getImageUrl(viewerImage.id)"
					:alt="viewerImage.original_name"
					class="xfiles-viewer__img">

				<!-- Next button -->
				<button
					v-if="hasNext"
					class="xfiles-viewer__nav xfiles-viewer__nav--next"
					:aria-label="t('xfiles', 'Next image')"
					@click="viewerNext">
					<ChevronRightIcon :size="36" />
				</button>

				<div class="xfiles-viewer__counter">
					{{ viewerIndex + 1 }} / {{ images.length }}
				</div>
				<div class="xfiles-viewer__actions">
					<NcButton
						type="secondary"
						:aria-label="t('xfiles', 'Download')"
						:href="getImageUrl(viewerImage.id)"
						:download="viewerImage.original_name">
						<template #icon>
							<DownloadIcon :size="20" />
						</template>
						{{ t('xfiles', 'Download') }}
					</NcButton>
					<NcButton
						type="error"
						:aria-label="t('xfiles', 'Delete')"
						@click="onDeleteFromViewer">
						<template #icon>
							<DeleteIcon :size="20" />
						</template>
						{{ t('xfiles', 'Delete') }}
					</NcButton>
				</div>
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

		<!-- Settings panel -->
		<NcModal
			v-if="showSettings"
			:name="t('xfiles', 'Settings')"
			@close="showSettings = false">
			<SettingsView
				:initial-auto-lock-seconds="autoLockSeconds"
				:initial-max-file-size-mb="maxFileSizeMb"
				@password-changed="onPasswordChanged" />
		</NcModal>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import CheckboxBlankIcon from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import CheckboxIcon from 'vue-material-design-icons/CheckboxMarked.vue'
import CheckboxMultipleIcon from 'vue-material-design-icons/CheckboxMultipleMarked.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import ImageIcon from 'vue-material-design-icons/Image.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { listImages, uploadImage, deleteImage, lockVault, getImageUrl, getThumbnailUrl, getVaultStatus } from '../services/api.js'
import SettingsView from './SettingsView.vue'

export default {
	name: 'UnlockedView',
	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcModal,
		CheckboxBlankIcon,
		CheckboxIcon,
		CheckboxMultipleIcon,
		ChevronLeftIcon,
		ChevronRightIcon,
		CogIcon,
		DeleteIcon,
		DownloadIcon,
		ImageIcon,
		LockIcon,
		PlusIcon,
		SettingsView,
	},
	data() {
		return {
			images: [],
			total: 0,
			loading: true,
			uploading: false,
			uploadCurrent: 0,
			uploadTotal: 0,
			uploadProgress: 0,
			viewerImage: null,
			showSettings: false,
			autoLockSeconds: 300,
			maxFileSizeMb: 50,
			remainingSeconds: 0,
			countdownInterval: null,
			selectMode: false,
			selected: [],
			dragging: false,
		}
	},
	computed: {
		viewerIndex() {
			if (!this.viewerImage) return -1
			return this.images.findIndex(i => i.id === this.viewerImage.id)
		},
		hasPrev() {
			return this.viewerIndex > 0
		},
		hasNext() {
			return this.viewerIndex >= 0 && this.viewerIndex < this.images.length - 1
		},
	},
	async mounted() {
		await this.fetchImages()
		await this.fetchSettings()
		this.startCountdown()
	},
	beforeDestroy() {
		this.stopCountdown()
	},
	methods: {
		t,
		getImageUrl,
		getThumbnailUrl,

		// --- Data fetching ---
		async fetchImages() {
			this.loading = true
			try {
				const data = await listImages(500, 0)
				this.images = data.images
				this.total = data.total
			} catch (e) {
				if (e.response?.status === 403) {
					this.$emit('locked')
					return
				}
				showError(t('xfiles', 'Failed to load images'))
			} finally {
				this.loading = false
			}
		},
		async fetchSettings() {
			try {
				const data = await getVaultStatus()
				if (data.auto_lock_seconds !== undefined) {
					this.autoLockSeconds = data.auto_lock_seconds
				}
				if (data.max_file_size_mb !== undefined) {
					this.maxFileSizeMb = data.max_file_size_mb
				}
				if (data.remaining_seconds !== undefined) {
					this.remainingSeconds = data.remaining_seconds
				}
			} catch (e) {
				// Use defaults
			}
		},

		// --- Countdown timer ---
		startCountdown() {
			if (this.autoLockSeconds <= 0) return
			this.countdownInterval = setInterval(() => {
				if (this.remainingSeconds > 0) {
					this.remainingSeconds--
				} else {
					this.$emit('locked')
				}
			}, 1000)
		},
		stopCountdown() {
			if (this.countdownInterval) {
				clearInterval(this.countdownInterval)
				this.countdownInterval = null
			}
		},
		formatCountdown(seconds) {
			const m = Math.floor(seconds / 60)
			const s = seconds % 60
			return `${m}:${s.toString().padStart(2, '0')}`
		},

		// --- Upload ---
		triggerUpload() {
			this.$refs.fileInput.click()
		},
		async onFileSelected(event) {
			const files = Array.from(event.target.files)
			if (files.length === 0) return
			await this.processUploads(files)
			this.$refs.fileInput.value = ''
		},
		async processUploads(files) {
			this.uploading = true
			this.uploadTotal = files.length
			this.uploadCurrent = 0
			this.uploadProgress = 0
			let successCount = 0

			for (const file of files) {
				this.uploadCurrent++
				this.uploadProgress = Math.round((this.uploadCurrent / this.uploadTotal) * 100)
				try {
					await uploadImage(file)
					successCount++
				} catch (e) {
					const msg = e.response?.data?.error || file.name
					showError(t('xfiles', 'Failed to upload: {name}', { name: msg }))
				}
			}

			this.uploading = false
			this.uploadProgress = 0

			if (successCount > 0) {
				showSuccess(t('xfiles', '{count} image(s) uploaded', { count: successCount }))
				await this.fetchImages()
				await this.fetchSettings() // refresh remaining_seconds
			}
		},

		// --- Drag and drop ---
		onDragOver() {
			this.dragging = true
		},
		onDragLeave() {
			this.dragging = false
		},
		async onDrop(event) {
			this.dragging = false
			const files = Array.from(event.dataTransfer.files).filter(
				f => f.type.startsWith('image/'),
			)
			if (files.length === 0) {
				showError(t('xfiles', 'Only image files are accepted'))
				return
			}
			await this.processUploads(files)
		},

		// --- Selection ---
		toggleSelectMode() {
			this.selectMode = !this.selectMode
			if (!this.selectMode) {
				this.selected = []
			}
		},
		isSelected(id) {
			return this.selected.includes(id)
		},
		onTileClick(image, event) {
			if (this.selectMode) {
				if (this.isSelected(image.id)) {
					this.selected = this.selected.filter(i => i !== image.id)
				} else {
					this.selected.push(image.id)
				}
			} else {
				this.openViewer(image)
			}
		},
		async onBatchDelete() {
			const count = this.selected.length
			if (!confirm(t('xfiles', 'Delete {count} image(s) permanently?', { count }))) {
				return
			}

			let deleted = 0
			for (const id of this.selected) {
				try {
					await deleteImage(id)
					this.images = this.images.filter(i => i.id !== id)
					this.total--
					deleted++
				} catch (e) {
					// Continue with rest
				}
			}

			this.selected = []
			this.selectMode = false
			showSuccess(t('xfiles', '{count} image(s) deleted', { count: deleted }))
		},

		// --- Viewer ---
		openViewer(image) {
			this.viewerImage = image
		},
		viewerPrev() {
			if (this.hasPrev) {
				this.viewerImage = this.images[this.viewerIndex - 1]
			}
		},
		viewerNext() {
			if (this.hasNext) {
				this.viewerImage = this.images[this.viewerIndex + 1]
			}
		},
		onViewerKeydown(event) {
			if (event.key === 'ArrowLeft') {
				this.viewerPrev()
			} else if (event.key === 'ArrowRight') {
				this.viewerNext()
			}
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
		async onDeleteFromViewer() {
			const image = this.viewerImage
			if (!image) return
			if (!confirm(t('xfiles', 'Delete "{name}" permanently?', { name: image.original_name }))) {
				return
			}
			try {
				await deleteImage(image.id)
				this.images = this.images.filter(i => i.id !== image.id)
				this.total--
				this.viewerImage = null
				showSuccess(t('xfiles', 'Image deleted'))
			} catch (e) {
				showError(t('xfiles', 'Failed to delete image'))
			}
		},

		// --- Lock ---
		async onLock() {
			await lockVault()
			this.$emit('locked')
		},
		onPasswordChanged() {
			this.showSettings = false
			this.$emit('locked')
		},

		// --- Formatting ---
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
	position: relative;
}

.xfiles-unlocked__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 16px;
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
}

.xfiles-unlocked__header-left {
	display: flex;
	align-items: center;
	gap: 12px;
}

.xfiles-unlocked__header h2 {
	margin: 0;
	font-size: 1.2em;
}

.xfiles-unlocked__countdown {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.xfiles-unlocked__actions {
	display: flex;
	gap: 4px;
	align-items: center;
}

.xfiles-unlocked__select-active {
	color: var(--color-primary-element) !important;
}

/* Progress bar */
.xfiles-unlocked__progress {
	position: relative;
	height: 24px;
	background: var(--color-background-dark);
	flex-shrink: 0;
}

.xfiles-unlocked__progress-bar {
	height: 100%;
	background: var(--color-primary-element);
	transition: width 0.3s;
}

.xfiles-unlocked__progress-text {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	font-size: 0.8em;
	font-weight: 500;
	color: var(--color-primary-element-text);
}

/* Drag and drop overlay */
.xfiles-unlocked__dropzone {
	position: absolute;
	inset: 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 12px;
	background: rgba(var(--color-primary-element-rgb, 0, 130, 201), 0.1);
	border: 3px dashed var(--color-primary-element);
	border-radius: var(--border-radius-large);
	z-index: 10;
	color: var(--color-primary-element);
	font-size: 1.1em;
	pointer-events: none;
}

.xfiles-unlocked__loading {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
}

.xfiles-unlocked__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
	gap: 8px;
	padding: 12px;
	overflow-y: auto;
	flex: 1;
	min-height: 0;
}

.xfiles-unlocked__tile {
	position: relative;
	overflow: hidden;
	border-radius: var(--border-radius);
	cursor: pointer;
	background: var(--color-background-dark);
}

/* padding-bottom trick ensures perfect squares regardless of aspect-ratio support */
.xfiles-unlocked__tile::before {
	content: '';
	display: block;
	padding-bottom: 100%;
}

.xfiles-unlocked__tile--selected {
	outline: 3px solid var(--color-primary-element);
	outline-offset: -3px;
}

.xfiles-unlocked__thumb {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.xfiles-unlocked__tile-check {
	position: absolute;
	top: 6px;
	left: 6px;
	color: var(--color-primary-element);
	filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.5));
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
	position: relative;
}

.xfiles-viewer__img {
	max-width: 100%;
	max-height: 60vh;
	object-fit: contain;
	border-radius: var(--border-radius);
}

.xfiles-viewer__nav {
	position: absolute;
	top: 50%;
	transform: translateY(-50%);
	background: rgba(0, 0, 0, 0.5);
	color: white;
	border: none;
	border-radius: 50%;
	width: 44px;
	height: 44px;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	z-index: 10;
	transition: background 0.15s;
}

.xfiles-viewer__nav:hover {
	background: rgba(0, 0, 0, 0.75);
}

.xfiles-viewer__nav--prev {
	left: 12px;
}

.xfiles-viewer__nav--next {
	right: 12px;
}

.xfiles-viewer__counter {
	margin-top: 8px;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.xfiles-viewer__actions {
	display: flex;
	gap: 8px;
	margin-top: 12px;
}

.xfiles-viewer__info {
	display: flex;
	gap: 16px;
	margin-top: 12px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
</style>
