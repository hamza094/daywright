<script>
import { Bar } from 'vue-chartjs';

export default {
  extends: Bar,
  data() {
    return {
      chartData: {
        labels: [],
        datasets: [
          {
            label: ['Active Projects', 'Trashed Projects'],
            backgroundColor: ['blue', 'red'],
            data: [],
          },
          {
            label: ['Active Tasks', 'Trashed Tasks'],
            backgroundColor: ['green', 'yellow'],
            data: [],
          },
        ],
      },
      totalProjects: 0,
      totalTasks: 0,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          xAxes: [{ stacked: false }],
          yAxes: [{ stacked: false }],
        },
      },
    };
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    async fetchData() {
      try {
        const response = await axios.get('/data');
        const data = response.data;

        this.chartData.labels = data.map((item) => item.month);

        this.chartData.datasets[0].data = data.map((item) => [item.active_projects, item.trashed_projects]);
        this.chartData.datasets[1].data = data.map((item) => [item.active_tasks, item.trashed_tasks]);

        this.renderChart(this.chartData, this.options);
      } catch (error) {
        this.handleErrorResponse(error);
      }
    },
  },
};
</script>
