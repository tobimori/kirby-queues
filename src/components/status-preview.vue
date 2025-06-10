<template>
	<span
		class="k-queue-status-preview k-text-field-preview"
		:data-status="value"
	>
		<k-icon :type="icon" />
		<span class="k-queue-status-preview-text">
			{{ $t(`queues.status.${value}`) }}
		</span>
	</span>
</template>

<script setup>
import { computed } from "kirbyuse"

const props = defineProps({
	value: String
})

const icon = computed(() => {
	const icons = {
		pending: "clock",
		running: "play",
		completed: "check",
		failed: "alert"
	}

	return icons[props.value] || "circle"
})
</script>

<style>
.k-queue-status-preview {
	display: flex;
	align-items: center;
	gap: 0.375rem;
}

.k-queue-status-preview .k-icon {
	width: 1rem;
	height: 1rem;
}

.k-queue-status-preview[data-status="pending"] .k-icon {
	color: var(--color-yellow-600);
}

.k-queue-status-preview[data-status="running"] .k-icon {
	color: var(--color-blue-600);
}

.k-queue-status-preview[data-status="completed"] .k-icon {
	color: var(--color-green-600);
}

.k-queue-status-preview[data-status="failed"] .k-icon {
	color: var(--color-red-600);
}

.k-queue-status-preview-text {
	font-size: var(--text-sm);
	color: var(--color-text);
}
</style>
