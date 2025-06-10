<template>
	<k-panel-inside class="k-queues-view">
		<k-header :bottom="false">
			{{ $t("queues.jobs") }}
			<k-button-group slot="left">
				<k-dropdown>
					<k-button icon="calendar" @click="$refs.timerange.toggle()">
						{{ timeRangeLabel }}
					</k-button>
					<k-dropdown-content ref="timerange" align="left">
						<k-dropdown-item
							v-for="range in timeRanges"
							:key="range.value"
							:current="timeRange === range.value"
							@click="setTimeRange(range.value)"
						>
							{{ range.label }}
						</k-dropdown-item>
					</k-dropdown-content>
				</k-dropdown>
				<k-button icon="refresh" size="sm" @click="refresh">
					{{ $t("queues.action.refresh") }}
				</k-button>
			</k-button-group>
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
			@option="onAction"
		/>

		<footer
			v-if="currentJobs.length > 0 && currentTotal > limit"
			class="k-bar k-collection-footer"
		>
			<k-pagination
				:page="page"
				:total="currentTotal"
				:limit="limit"
				:details="true"
				align="right"
				@paginate="paginate"
			/>
		</footer>
	</k-panel-inside>
</template>

<script>
export default {
	props: {
		statistics: Object,
		jobs: Array,
		total: Number,
		status: {
			type: String,
			default: "pending"
		}
	},
	data() {
		return {
			loading: false,
			currentJobs: this.transformJobs(this.jobs || []),
			currentTotal: this.total || 0,
			page: 1,
			limit: 50,
			timeRange: "24h"
		}
	},
	computed: {
		statsReports() {
			const stats = this.statistics || { total: 0, by_status: {} }

			return [
				{
					info: this.$t("queues.total"),
					value: String(stats.total || 0)
				},
				{
					info: this.$t("queues.status.pending"),
					value: String(stats.by_status?.pending || 0),
					theme: stats.by_status?.pending > 0 ? "yellow" : null
				},
				{
					info: this.$t("queues.status.running"),
					value: String(stats.by_status?.running || 0),
					theme: stats.by_status?.running > 0 ? "blue" : null
				},
				{
					info: this.$t("queues.status.completed"),
					value: String(stats.by_status?.completed || 0),
					theme: stats.by_status?.completed > 0 ? "positive" : null
				},
				{
					info: this.$t("queues.status.failed"),
					value: String(stats.by_status?.failed || 0),
					theme: stats.by_status?.failed > 0 ? "negative" : null
				}
			]
		},
		currentStatus() {
			// Use status prop to determine current tab
			if (this.status === "completed") return "jobs"
			return this.status
		},
		statusTabs() {
			return [
				{
					name: "pending",
					label: this.$t("queues.status.pending"),
					icon: "clock",
					link: "/queues"
				},
				{
					name: "running",
					label: this.$t("queues.status.running"),
					icon: "play",
					link: "/queues/running"
				},
				{
					name: "jobs",
					label: this.$t("queues.status.completed"),
					icon: "check",
					link: "/queues/completed"
				},
				{
					name: "failed",
					label: this.$t("queues.status.failed"),
					icon: "alert",
					link: "/queues/failed"
				}
			]
		},
		timeRanges() {
			return [
				{ value: "1h", label: this.$t("queues.timeRange.lastHour") },
				{ value: "24h", label: this.$t("queues.timeRange.last24Hours") },
				{ value: "7d", label: this.$t("queues.timeRange.last7Days") },
				{ value: "30d", label: this.$t("queues.timeRange.last30Days") },
				{ value: "all", label: this.$t("queues.timeRange.allTime") }
			]
		},
		timeRangeLabel() {
			const range = this.timeRanges.find((r) => r.value === this.timeRange)
			return range ? range.label : this.$t("queues.timeRange.last24Hours")
		},
		columns() {
			return {
				name: {
					label: this.$t("queues.job.name"),
					type: "text"
				},
				queue: {
					label: this.$t("queues.job.queue"),
					type: "text"
				},
				created_at: {
					label: this.$t("queues.job.created"),
					type: "date",
					display: "DD.MM.YYYY HH:mm"
				},
				attempts: {
					label: this.$t("queues.job.attempts"),
					type: "number",
					mobile: false
				}
			}
		},
		offset() {
			return (this.page - 1) * this.limit
		},
		jobOptions() {
			return (row) => {
				const options = []

				if (this.status === "failed") {
					options.push({
						icon: "refresh",
						text: this.$t("queues.action.retry"),
						click: () => this.onAction(row, "retry")
					})
				}

				if (this.status !== "running") {
					options.push({
						icon: "trash",
						text: this.$t("queues.action.delete"),
						click: () => this.onAction(row, "delete")
					})
				}

				return options
			}
		}
	},
	watch: {
		// Watch for prop changes from server-side navigation
		jobs(newJobs) {
			this.currentJobs = this.transformJobs(newJobs || [])
		},
		total(newTotal) {
			this.currentTotal = newTotal || 0
		}
	},
	methods: {
		transformJobs(jobs) {
			return jobs.map((job) => ({
				id: job.id,
				name: job.name || job.type,
				type: job.type,
				queue: job.queue,
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
		},
		async loadStats() {
			try {
				const response = await this.$api.get("queues/stats")
				this.statistics = response
			} catch (error) {
				this.$panel.notification.error(error.message)
			}
		},
		async loadJobs() {
			this.loading = true

			try {
				const response = await this.$api.get("queues/jobs", {
					status: this.status,
					limit: this.limit,
					offset: this.offset
				})

				this.currentJobs = this.transformJobs(response.jobs)

				this.currentTotal = response.total
			} catch (error) {
				this.$panel.notification.error(error.message)
			} finally {
				this.loading = false
			}
		},
		async onAction(item, action) {
			if (action === "retry") {
				try {
					await this.$api.post(`queues/jobs/${item.id}/retry`)
					this.$panel.notification.success("Job queued for retry")
					this.refresh()
				} catch (error) {
					this.$panel.notification.error(error.message)
				}
			} else if (action === "delete") {
				try {
					await this.$panel.dialog.open({
						component: "k-remove-dialog",
						props: {
							text: "Do you really want to delete this job?"
						}
					})

					await this.$api.delete(`queues/jobs/${item.id}`)
					this.$panel.notification.success("Job deleted")
					this.refresh()
				} catch (error) {
					if (error.message !== "The dialog has been canceled") {
						this.$panel.notification.error(error.message)
					}
				}
			}
		},
		paginate(pagination) {
			this.page = pagination.page
			this.loadJobs()
		},
		refresh() {
			this.loadStats()
			this.loadJobs()
		},
		setTimeRange(range) {
			this.timeRange = range
			this.$refs.timerange.close()
			this.refresh()
		}
	}
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
	overflow: hidden;
}
</style>
