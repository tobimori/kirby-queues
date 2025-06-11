import View from "./components/view.vue";
import StatusPreview from "./components/status-preview.vue";
import AttemptsPreview from "./components/attempts-preview.vue";
import JobDrawer from "./components/job-drawer.vue";

panel.plugin("tobimori/queues", {
  components: {
    "k-queues-view": View,
    "k-queue-status-field-preview": StatusPreview,
    "k-queue-attempts-field-preview": AttemptsPreview,
    "k-queues-job-drawer": JobDrawer
  }
})
