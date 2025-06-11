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
							{{ jobTypeLabel }}
						</k-button>
						<k-dropdown-content ref="jobtype" align-x="end">
							<k-dropdown-item
								:current="!currentJobType"
								@click="selectJobType('')"
							>
								{{ $t("queues.jobType.all") }}
							</k-dropdown-item>
							<hr />
							<k-dropdown-item
								v-for="type in jobTypes"
								:key="type.value"
								:current="currentJobType === type.value"
								@click="selectJobType(type.value)"
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
							{{ timeRangeLabel }}
						</k-button>
						<k-dropdown-content ref="timerange" align-x="end">
							<k-dropdown-item
								v-for="range in timeRanges"
								:key="range.value"
								:current="currentTimeRange === range.value"
								@click="selectTimeRange(range.value)"
							>
								{{ range.label }}
							</k-dropdown-item>
						</k-dropdown-content>
					</k-dropdown>
					<k-button
						icon="refresh"
						variant="filled"
						size="sm"
						@click="refresh"
					/>
				</k-button-group>
			</template>
		</k-header>

		<k-stats :reports="statsReports" size="large" />

		<k-tabs :tab="currentStatus" :tabs="statusTabs" />

		<div v-if="loading" class="k-queues-loading">
			<k-loader />
		</div>

		<k-empty v-else-if="currentJobs.length === 0" icon="list-bullet">
			{{ $t("queues.empty") }}
		</k-empty>

		<k-table
			v-else
			:columns="columns"
			:rows="currentJobs"
			:options="jobOptions"
			@header="onHeader"
			@option="onAction"
		>
			<template #header="{ columnIndex, label }">
				<span
					v-if="columns[columnIndex] && columns[columnIndex].sortable"
					data-sortable="true"
				>
					{{ label }}
					<k-icon
						v-if="columnIndex === currentSortBy"
						:type="currentSortOrder === 'asc' ? 'angle-up' : 'angle-down'"
					/>
				</span>
				<span v-else>{{ label }}</span>
			</template>
		</k-table>

		<footer
			v-if="currentJobs.length > 0 && currentTotal > limit"
			class="k-bar k-collection-footer"
		>
			<k-pagination
				:page="currentPage"
				:total="currentTotal"
				:limit="limit"
				:details="true"
				align="right"
				@paginate="paginate"
			/>
		</footer>
	</k-panel-inside>
</template>

<script setup>
import {
	computed,
	onBeforeUnmount,
	onMounted,
	ref,
	watch,
	useApi,
	usePanel,
	useApp
} from "kirbyuse"

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
const api = useApi()
const app = useApp()

const loading = ref(false)
const currentJobs = ref(transformJobs(props.jobs || []))
const currentTotal = ref(props.total || 0)
const currentPage = ref(props.page)
const limit = 50
const currentTimeRange = ref(props.timeRange)
const currentSortBy = ref(props.sortBy)
const currentSortOrder = ref(props.sortOrder)
const currentJobType = ref(props.jobType)
let refreshInterval = null

const jobtype = ref(null)
const timerange = ref(null)

const statsReports = computed(() => {
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

const currentStatus = computed(() => {
	if (props.status === "completed") return "jobs"
	return props.status
})

const statusTabs = computed(() => {
	const params = new URLSearchParams()
	if (currentTimeRange.value !== "24h") {
		params.set("timeRange", currentTimeRange.value)
	}
	if (currentJobType.value) {
		params.set("jobType", currentJobType.value)
	}
	if (currentSortBy.value !== "created_at") {
		params.set("sortBy", currentSortBy.value)
	}
	if (currentSortOrder.value !== "desc") {
		params.set("sortOrder", currentSortOrder.value)
	}
	const queryString = params.toString() ? "?" + params.toString() : ""

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
			name: "jobs",
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

const timeRanges = computed(() => [
	{ value: "1h", label: panel.t("queues.timeRange.lastHour") },
	{ value: "24h", label: panel.t("queues.timeRange.last24Hours") },
	{ value: "7d", label: panel.t("queues.timeRange.last7Days") },
	{ value: "30d", label: panel.t("queues.timeRange.last30Days") },
	{ value: "all", label: panel.t("queues.timeRange.allTime") }
])

const timeRangeLabel = computed(() => {
	const range = timeRanges.value.find((r) => r.value === currentTimeRange.value)
	return range ? range.label : panel.t("queues.timeRange.last24Hours")
})

const jobTypeLabel = computed(() => {
	if (!currentJobType.value) {
		return panel.t("queues.jobType.all")
	}
	const type = props.jobTypes.find(t => t.value === currentJobType.value)
	return type ? type.label : currentJobType.value
})

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

const jobOptions = computed(() => {
	return (row) => {
		const options = []

		if (props.status === "failed") {
			options.push({
				icon: "refresh",
				text: panel.t("queues.action.retry"),
				click: () => onAction(row, "retry")
			})
		}

		if (props.status !== "running") {
			options.push({
				icon: "trash",
				text: panel.t("queues.action.delete"),
				click: () => onAction(row, "delete")
			})
		}

		return options
	}
})

watch(
	() => props.jobs,
	(newJobs) => {
		currentJobs.value = transformJobs(newJobs || [])
	}
)

watch(
	() => props.total,
	(newTotal) => {
		currentTotal.value = newTotal || 0
	}
)

watch(
	() => props.page,
	(newPage) => {
		currentPage.value = newPage
	}
)

watch(
	() => props.timeRange,
	(newTimeRange) => {
		currentTimeRange.value = newTimeRange
	}
)

watch(
	() => props.sortBy,
	(newSortBy) => {
		currentSortBy.value = newSortBy
	}
)

watch(
	() => props.sortOrder,
	(newSortOrder) => {
		currentSortOrder.value = newSortOrder
	}
)

watch(
	() => props.jobType,
	(newJobType) => {
		currentJobType.value = newJobType
	}
)

onMounted(() => {
	refreshInterval = setInterval(() => {
		refresh()
	}, 15000)
})

onBeforeUnmount(() => {
	if (refreshInterval) {
		clearInterval(refreshInterval)
	}
})

function transformJobs(jobs) {
	return jobs.map((job) => ({
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
}

async function onAction(item, action) {
	if (action === "retry") {
		try {
			await api.post(`queues/jobs/${item.id}/retry`)
			panel.notification.success("Job queued for retry")
			refresh()
		} catch (error) {
			panel.notification.error(error.message)
		}
	} else if (action === "delete") {
		try {
			await panel.dialog.open({
				component: "k-remove-dialog",
				props: {
					text: "Do you really want to delete this job?"
				}
			})

			await api.delete(`queues/jobs/${item.id}`)
			panel.notification.success("Job deleted")
			refresh()
		} catch (error) {
			if (error.message !== "The dialog has been canceled") {
				panel.notification.error(error.message)
			}
		}
	}
}

function paginate(pagination) {
	const path = window.location.pathname.replace(/^\/panel/, "")
	const params = new URLSearchParams()
	params.set("page", pagination.page.toString())
	if (currentTimeRange.value !== "24h") {
		params.set("timeRange", currentTimeRange.value)
	}
	if (currentJobType.value) {
		params.set("jobType", currentJobType.value)
	}
	if (currentSortBy.value !== "created_at") {
		params.set("sortBy", currentSortBy.value)
	}
	if (currentSortOrder.value !== "desc") {
		params.set("sortOrder", currentSortOrder.value)
	}
	app.$go(path + "?" + params.toString())
}

function refresh() {
	app.$reload()
}

function selectTimeRange(range) {
	timerange.value.close()
	const path = window.location.pathname.replace(/^\/panel/, "")
	const params = new URLSearchParams()
	params.set("timeRange", range)
	params.set("page", "1")
	if (currentJobType.value) {
		params.set("jobType", currentJobType.value)
	}
	if (currentSortBy.value !== "created_at") {
		params.set("sortBy", currentSortBy.value)
	}
	if (currentSortOrder.value !== "desc") {
		params.set("sortOrder", currentSortOrder.value)
	}
	app.$go(path + "?" + params.toString())
}

function selectJobType(type) {
	jobtype.value.close()
	const path = window.location.pathname.replace(/^\/panel/, "")
	const params = new URLSearchParams()
	if (currentTimeRange.value !== "24h") {
		params.set("timeRange", currentTimeRange.value)
	}
	params.set("page", "1")
	if (type) {
		params.set("jobType", type)
	}
	if (currentSortBy.value !== "created_at") {
		params.set("sortBy", currentSortBy.value)
	}
	if (currentSortOrder.value !== "desc") {
		params.set("sortOrder", currentSortOrder.value)
	}
	app.$go(path + "?" + params.toString())
}

function onHeader(event) {
	console.log("onHeader event:", event)
	console.log(
		"Current sortBy:",
		currentSortBy.value,
		"sortOrder:",
		currentSortOrder.value
	)

	const columnKey = event.columnIndex

	console.log(
		"Column clicked:",
		columnKey,
		"sortable:",
		columns.value[columnKey]?.sortable
	)

	if (!columns.value[columnKey]?.sortable) {
		return
	}

	let newSortBy = columnKey
	let newSortOrder = "asc"

	if (currentSortBy.value === columnKey) {
		if (currentSortOrder.value === "asc") {
			newSortOrder = "desc"
		} else if (currentSortOrder.value === "desc") {
			if (columnKey === "created_at") {
				newSortOrder = "asc"
			} else {
				newSortBy = "created_at"
				newSortOrder = "desc"
			}
		}
	}

	console.log("New sortBy:", newSortBy, "sortOrder:", newSortOrder)

	const path = window.location.pathname.replace(/^\/panel/, "")
	const params = new URLSearchParams()
	if (currentTimeRange.value !== "24h") {
		params.set("timeRange", currentTimeRange.value)
	}
	params.set("page", "1")
	if (currentJobType.value) {
		params.set("jobType", currentJobType.value)
	}
	if (newSortBy !== "created_at") {
		params.set("sortBy", newSortBy)
	}
	if (newSortOrder !== "desc") {
		params.set("sortOrder", newSortOrder)
	}
	console.log("Navigating to:", path + "?" + params.toString())
	app.$go(path + "?" + params.toString())
}
</script>

<style>
.k-queues-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 20rem;
}

.k-queues-view .k-header {
	margin-bottom: var(--spacing-3);
}

.k-queues-view .k-stats {
	margin-bottom: var(--spacing-6);
}

.k-queues-view .k-tabs {
	margin-bottom: var(--spacing-6);
}

.k-queues-view .k-table {
	overflow: clip;
}

.k-queues-view .k-table-column {
	cursor: default;
}

.k-queues-view .k-table-column:has(span[data-sortable]) {
	cursor: pointer;
}

.k-queues-view th.k-table-column > span {
	display: inline-flex;
	width: 100%;
	align-items: center;
	justify-content: space-between;
}
</style>
