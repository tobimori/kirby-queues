import View from "./components/view.vue";
import StatusPreview from "./components/status-preview.vue";
import AttemptsPreview from "./components/attempts-preview.vue";

panel.plugin("tobimori/queues", {
  components: {
    "k-queues-view": View,
    "k-queue-status-field-preview": StatusPreview,
    "k-queue-attempts-field-preview": AttemptsPreview
  }
})
