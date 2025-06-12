<script setup>
import { computed, usePanel } from "kirbyuse"
import { disabled, icon, id, options } from "kirbyuse/props"

const props = defineProps({
	...disabled,
	...icon,
	...id,
	...options,
	/**
	 * An array of breadcrumb items
	 */
	breadcrumb: {
		default: () => [],
		type: Array
	},
	/**
	 * The name of the currently active tab
	 */
	tab: {
		type: String
	},
	/**
	 * An object with tab definitions.
	 */
	tabs: {
		default: () => ({}),
		type: Object
	},
	/**
	 * The default title for the drawer header
	 */
	title: String,
	/**
	 * @private
	 */
	visible: {
		default: false,
		type: Boolean
	},
	// Job data
	job: {
		type: Object,
		required: true
	},
	value: {
		type: Object,
		default: () => ({})
	},
	submitButton: [String, Boolean]
})

defineEmits(["cancel", "crumb", "input", /*"submit",*/ "tab"])

const panel = usePanel()

const reports = computed(() => {
	const status = props.job?.status || "pending"
	const themes = {
		pending: "warning",
		running: "info",
		completed: "positive",
		failed: "negative"
	}

	return [
		{
			info: panel.t("queues.job.status"),
			value: panel.t("queues.status." + status),
			theme: themes[status]
		},
		{
			info: panel.t("queues.job.queue"),
			value: props.job?.queue || "default"
		},
		{
			info: panel.t("queues.job.attempts"),
			value: `${props.job?.attempts || 0} / ${props.job?.max_attempts || 3}`
		},
		{
			info: panel.t("queues.job.created"),
			value: new Date(props.job?.created_at * 1000).toLocaleString()
		}
	]
})
</script>

<template>
	<k-drawer
		ref="drawer"
		class="k-queues-job-drawer"
		v-bind="$props"
		@cancel="$emit('cancel')"
		@crumb="$emit('crumb', $event)"
		@submit="$emit('cancel')"
		@tab="$emit('tab', $event)"
	>
		<k-stats :reports="reports" size="small" />

		<k-section v-if="props.job?.error" :label="$t('queues.job.error')">
			<k-box theme="negative" class="k-queues-job-error">
				{{ props.job?.error }}
			</k-box>
		</k-section>

		<k-section
			v-if="JSON.stringify(props.job?.payload) !== '[]'"
			:label="$t('queues.drawer.payload')"
		>
			<k-code language="json">{{
				JSON.stringify(props.job?.payload || {}, null, 2)
			}}</k-code>
		</k-section>

		<k-section
			v-if="props?.job?.logs?.length > 0"
			:label="$t('queues.drawer.logs')"
		>
			<div class="k-queues-job-logs">
				<div
					v-for="(log, index) in props.job.logs"
					:key="index"
					class="k-queues-job-log-entry"
					:data-level="log.level"
				>
					<time class="k-queues-job-log-time">{{
						new Date(log.timestamp * 1000).toLocaleString()
					}}</time>
					<span class="k-queues-job-log-level">{{ log.level }}</span>
					<span class="k-queues-job-log-message">{{ log.message }}</span>
				</div>
			</div>
		</k-section>

		<k-section v-else :label="$t('queues.drawer.logs')">
			<k-empty icon="list-bullet">
				{{ $t("queues.drawer.logs.empty") }}
			</k-empty>
		</k-section>
	</k-drawer>
</template>

<style>
.k-queues-job-drawer {
	.k-drawer-body {
		padding: var(--spacing-6);
	}

	.k-stats {
		margin-bottom: var(--spacing-6);
		grid-template-columns: repeat(2, 1fr);
	}

	.k-code {
		max-height: 300px;
		overflow: auto;
		font-size: var(--text-xs);
	}
}

.k-queues-job-error {
	margin-bottom: var(--spacing-6);
}

.k-queues-job-logs {
	background: var(--color-black);
	border-radius: var(--rounded);
	padding: 0 var(--spacing-3);
	max-height: 400px;
	overflow-y: auto;
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	line-height: 1.5;
}

.k-queues-job-log-entry {
	display: flex;
	gap: var(--spacing-3);
	padding: 2px var(--spacing-2);
	margin: 0 calc(var(--spacing-3) * -1);
	line-height: 1.5;

	&[data-level="info"] {
		background: var(--color-blue-900);

		.k-queues-job-log-level {
			color: var(--color-blue-400);
		}
	}

	&[data-level="warning"] {
		background: var(--color-yellow-900);

		.k-queues-job-log-level {
			color: var(--color-yellow-400);
		}
	}

	&[data-level="error"] {
		background: var(--color-red-900);

		.k-queues-job-log-level {
			color: var(--color-red-400);
		}
	}

	&[data-level="debug"] .k-queues-job-log-level {
		color: var(--color-gray-500);
	}
}

.k-queues-job-log-time {
	color: var(--color-gray-500);
	white-space: nowrap;
	min-width: 8ch;
}

.k-queues-job-log-level {
	font-weight: var(--font-medium);
	text-transform: uppercase;
	white-space: nowrap;
	min-width: 5ch;
}

.k-queues-job-log-message {
	flex: 1;
	word-break: break-word;
	color: var(--color-white);
}
</style>
