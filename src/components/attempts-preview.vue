<script setup>
defineProps({
	value: {
		type: Number,
		default: 0
	}
})
</script>

<template>
	<span class="k-queue-attempts-preview k-text-field-preview">
		<template v-if="value > 0">
			{{ value }}
			<k-icon :title="title" type="refresh" />
		</template>
		<p v-else>–</p>
	</span>
</template>

<style>
.k-queue-attempts-preview {
	display: flex;
	justify-content: space-between;
	flex-direction: row-reverse;
	gap: var(--spacing-2);

	> .k-icon {
		--icon-color: var(--color-gray-400);
		transform: scale(0.8);
	}
}
</style>
