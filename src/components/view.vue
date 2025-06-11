<script setup>
import { computed, ref, usePanel, useApp } from "kirbyuse"

const props = defineProps({
	statistics: Object,
	jobs: Array,
	total: Number,
	status: {
		type: String,
		default: "pending"
	},
	page: {
		type: Number,
		default: 1
	},
	timeRange: {
		type: String,
		default: "24h"
	},
	sortBy: {
		type: String,
		default: "created_at"
	},
	sortOrder: {
		type: String,
		default: "desc"
	},
	jobType: {
		type: String,
		default: ""
	},
	jobTypes: {
		type: Array,
		default: () => []
	}
})

const panel = usePanel()
const app = useApp()
const limit = 50

const transformedJobs = computed(() =>
	(props.jobs ?? []).map((job) => ({
		id: job.id,
		name: job.name || job.type,
		type: job.type,
		queue: job.queue,
		status: job.status,
		attempts: job.attempts || 0,
		created_at: job.created_at
			? new Date(job.created_at * 1000).toISOString()
			: null,
		started_at: job.started_at
			? new Date(job.started_at * 1000).toISOString()
			: null,
		completed_at: job.completed_at
			? new Date(job.completed_at * 1000).toISOString()
			: null,
		failed_at: job.failed_at
			? new Date(job.failed_at * 1000).toISOString()
			: null
	}))
)

const reports = computed(() => {
	const stats = props.statistics || { total: 0, by_status: {} }

	return [
		{
			info: panel.t("queues.total"),
			value: String(stats.total || 0)
		},
		{
			info: panel.t("queues.status.pending"),
			value: String(stats.by_status?.pending || 0),
			theme: stats.by_status?.pending > 0 ? "yellow" : null
		},
		{
			info: panel.t("queues.status.running"),
			value: String(stats.by_status?.running || 0),
			theme: stats.by_status?.running > 0 ? "blue" : null
		},
		{
			info: panel.t("queues.status.completed"),
			value: String(stats.by_status?.completed || 0),
			theme: stats.by_status?.completed > 0 ? "positive" : null
		},
		{
			info: panel.t("queues.status.failed"),
			value: String(stats.by_status?.failed || 0),
			theme: stats.by_status?.failed > 0 ? "negative" : null
		}
	]
})

function buildQueryString(overrides = {}) {
	const params = {
		timeRange: props.timeRange,
		jobType: props.jobType,
		sortBy: props.sortBy,
		sortOrder: props.sortOrder,
		page: props.page,
		...overrides
	}

	const defaults = {
		timeRange: "24h",
		jobType: "",
		sortBy: "created_at",
		sortOrder: "desc",
		page: 1
	}

	const queryParams = new URLSearchParams()

	for (const [key, value] of Object.entries(params)) {
		if (value && value !== defaults[key]) {
			queryParams.set(key, value.toString())
		}
	}

	return queryParams.toString() ? "?" + queryParams.toString() : ""
}

const tabs = computed(() => {
	const queryString = buildQueryString({ page: undefined })

	return [
		{
			name: "all",
			label: panel.t("queues.all"),
			icon: "list-bullet",
			link: "/queues" + queryString
		},
		{
			name: "pending",
			label: panel.t("queues.status.pending"),
			icon: "clock",
			link: "/queues/pending" + queryString
		},
		{
			name: "running",
			label: panel.t("queues.status.running"),
			icon: "play",
			link: "/queues/running" + queryString
		},
		{
			name: "completed",
			label: panel.t("queues.status.completed"),
			icon: "check",
			link: "/queues/completed" + queryString
		},
		{
			name: "failed",
			label: panel.t("queues.status.failed"),
			icon: "alert",
			link: "/queues/failed" + queryString
		}
	]
})

const timeRanges = [
	{ value: "1h", label: panel.t("queues.timeRange.lastHour") },
	{ value: "24h", label: panel.t("queues.timeRange.last24Hours") },
	{ value: "7d", label: panel.t("queues.timeRange.last7Days") },
	{ value: "30d", label: panel.t("queues.timeRange.last30Days") },
	{ value: "all", label: panel.t("queues.timeRange.allTime") }
]

const columns = computed(() => {
	const cols = {
		name: {
			label: panel.t("queues.job.name"),
			type: "text",
			sortable: true
		},
		queue: {
			label: panel.t("queues.job.queue"),
			type: "text",
			sortable: true
		}
	}

	if (props.status === "all") {
		cols.status = {
			label: panel.t("queues.job.status"),
			type: "queue-status",
			sortable: true
		}
	}

	cols.created_at = {
		label: panel.t("queues.job.created"),
		type: "date",
		display: "DD.MM.YYYY HH:mm",
		sortable: true
	}

	cols.attempts = {
		label: panel.t("queues.job.attempts"),
		type: "queue-attempts",
		mobile: false,
		sortable: true,
		width: "1/12"
	}

	return cols
})

function navigate(overrides = {}) {
	const path = window.location.pathname.replace(/^\/panel/, "")
	app.$go(path + buildQueryString(overrides))
}

function onHeader(event) {
	const columnKey = event.columnIndex

	if (!columns.value[columnKey]?.sortable) {
		return
	}

	let newSortBy = columnKey
	let newSortOrder = "asc"

	if (props.sortBy === columnKey) {
		if (props.sortOrder === "asc") {
			newSortOrder = "desc"
		} else if (props.sortOrder === "desc") {
			if (columnKey === "created_at") {
				newSortOrder = "asc"
			} else {
				newSortBy = "created_at"
				newSortOrder = "desc"
			}
		}
	}

	navigate({ sortBy: newSortBy, sortOrder: newSortOrder, page: 1 })
}
</script>

<template>
	<k-panel-inside class="k-queues-view">
		<k-header>
			{{ $t("queues.jobs") }}
			<template #buttons>
				<k-button-group>
					<k-dropdown v-if="jobTypes.length > 0">
						<k-button
							icon="filter"
							variant="filled"
							size="sm"
							@click="$refs.jobtype.toggle()"
						>
							{{
								props.jobTypes.find((t) => t.value === props.jobType)?.label ??
								$t("queues.jobType.all")
							}}
						</k-button>
						<k-dropdown-content ref="jobtype" align-x="end">
							<k-dropdown-item
								:current="!jobType"
								@click="navigate({ jobType: undefined, page: 1 })"
							>
								{{ $t("queues.jobType.all") }}
							</k-dropdown-item>
							<hr />
							<k-dropdown-item
								v-for="type in jobTypes"
								:key="type.value"
								:current="jobType === type.value"
								@click="navigate({ jobType: type.value, page: 1 })"
							>
								{{ type.label }}
							</k-dropdown-item>
						</k-dropdown-content>
					</k-dropdown>
					<k-dropdown>
						<k-button
							icon="calendar"
							variant="filled"
							size="sm"
							@click="$refs.timerange.toggle()"
						>
							{{
								timeRanges.find((r) => r.value === props.timeRange)?.label ??
								$t("queues.timeRange.last24Hours")
							}}
						</k-button>
						<k-dropdown-content ref="timerange" align-x="end">
							<k-dropdown-item
								v-for="range in timeRanges"
								:key="range.value"
								:current="timeRange === range.value"
								@click="navigate({ timeRange: range.value, page: 1 })"
							>
								{{ range.label }}
							</k-dropdown-item>
						</k-dropdown-content>
					</k-dropdown>
					<k-button
						icon="refresh"
						variant="filled"
						size="sm"
						@click="app.$reload()"
					/>
				</k-button-group>
			</template>
		</k-header>

		<k-stats :reports="reports" size="large" />

		<k-tabs :tab="props.status" :tabs="tabs" />

		<k-empty v-if="transformedJobs.length === 0" icon="list-bullet">
			{{ $t("queues.empty") }}
		</k-empty>

		<k-table
			v-else
			:columns="columns"
			:rows="transformedJobs"
			@header="onHeader"
		>
			<template #header="{ columnIndex, label }">
				<span
					v-if="columns[columnIndex] && columns[columnIndex].sortable"
					data-sortable="true"
				>
					{{ label }}
					<k-icon
						v-if="columnIndex === sortBy"
						:type="sortOrder === 'asc' ? 'angle-up' : 'angle-down'"
					/>
				</span>
				<span v-else>{{ label }}</span>
			</template>
			<template #options="{ row }">
				<k-button
					icon="dots"
					size="xs"
					@click="panel.drawer.open('queues/jobs/' + row.id)"
				/>
			</template>
		</k-table>

		<footer
			v-if="transformedJobs.length > 0 && total > limit"
			class="k-bar k-collection-footer"
		>
			<k-pagination
				:page="page"
				:total="total"
				:limit="limit"
				:details="true"
				align="right"
				@paginate="(pagination) => navigate({ page: pagination.page })"
			/>
		</footer>
	</k-panel-inside>
</template>

<style>
.k-queues-view {
	.k-header {
		margin-bottom: var(--spacing-3);
	}

	.k-stats {
		margin-bottom: var(--spacing-6);
	}

	.k-tabs {
		margin-bottom: var(--spacing-6);
	}

	.k-table {
		overflow: clip;
	}

	.k-table-column {
		cursor: default;

		&:has(span[data-sortable]) {
			cursor: pointer;
		}
	}

	th.k-table-column > span {
		display: inline-flex;
		width: 100%;
		align-items: center;
		justify-content: space-between;
	}
}
</style>
