<template>
  <div class="chart-container">
    <div class="chart-header d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Project Analytics</h5>
      <div class="chart-controls">
        <select v-model="selectedYear" class="form-control form-control-sm mr-2" :disabled="isRequestPending">
          <option value="">All Years</option>
          <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
        </select>
        <select v-model="selectedMonth" class="form-control form-control-sm mr-2" :disabled="isRequestPending">
          <option value="">All Months</option>
          <option v-for="(month, index) in months" :key="index" :value="index + 1">{{ month }}</option>
        </select>
      </div>
    </div>

    <div class="chart-wrapper" style="height: 300px">
      <div v-if="isLoading" class="d-flex justify-content-center align-items-center h-100">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>
      <canvas v-else ref="chartCanvas"></canvas>
    </div>

    <div class="chart-summary mt-3" v-if="projectStats && !isLoading">
      <div class="row">
        <div class="col-sm-6 col-md-3" v-for="stat in projectStats" :key="stat.label">
          <div class="metric-card text-center p-2">
            <div class="metric-value">{{ stat.value }}</div>
            <div class="metric-label">{{ stat.label }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Bar } from 'vue-chartjs';

export default {
  name: 'ProjectChart',
  extends: Bar,
  data() {
    return {
      selectedYear: '',
      selectedMonth: '',
      isLoading: false,
      isRequestPending: false,
      chartData: {
        labels: [],
        datasets: [],
      },
      projectStats: null,
      chartInstance: null,
      months: [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
      ],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          xAxes: [{ stacked: false }],
          yAxes: [
            {
              stacked: false,
              ticks: { beginAtZero: true },
            },
          ],
        },
        plugins: {
          legend: {
            position: 'top',
            labels: { usePointStyle: true },
          },
          tooltip: {
            mode: 'index',
            intersect: false,
          },
        },
      },
    };
  },
  computed: {
    availableYears() {
      const currentYear = new Date().getFullYear();
      const years = [];
      for (let year = currentYear; year >= currentYear - 4; year--) {
        years.push(year);
      }
      return years;
    },
  },
  watch: {
    selectedYear() {
      this.loadChartData();
    },
    selectedMonth() {
      this.loadChartData();
    },
  },
  mounted() {
    this.loadChartData();
  },
  beforeDestroy() {
    if (this.chartInstance) {
      this.chartInstance.destroy();
    }
  },
  methods: {
    async loadChartData() {
      // Prevent multiple simultaneous requests
      if (this.isRequestPending) {
        return;
      }

      try {
        this.isLoading = true;
        this.isRequestPending = true;

        const params = {};
        if (this.selectedYear) {
          params.year = this.selectedYear;
        }
        if (this.selectedMonth) {
          params.month = this.selectedMonth;
        }
        const response = await axios.get(`/dashboard/chart-data`, { params });
        // Set chartData and projectStats from the same response
        this.chartData = {
          labels: ['Active Projects', 'Trashed Projects', 'Member Projects'],
          datasets: [
            {
              label: 'Project Count',
              backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
              data: [
                response.data.data.active_projects || 0,
                response.data.data.trashed_projects || 0,
                response.data.data.member_projects || 0,
              ],
            },
          ],
        };
        this.projectStats = [
          { label: 'Active Projects', value: response.data.data.active_projects || 0 },
          { label: 'Trashed Projects', value: response.data.data.trashed_projects || 0 },
          { label: 'Member Projects', value: response.data.data.member_projects || 0 },
          { label: 'Total Projects', value: response.data.data.total_projects || 0 },
        ];
        this.renderChart();
      } catch (err) {
        if (process.env.NODE_ENV !== 'production') {
          console.error(err);
        }
        this.$vToastify.error('Failed to load chart data');
      } finally {
        this.isLoading = false;
        this.isRequestPending = false;
      }
    },
    renderChart() {
      this.$nextTick().then(() => {
        const canvas = this.$refs.chartCanvas;
        if (!canvas) {
          console.warn('Canvas not found yet');
          return;
        }

        const ctx = canvas.getContext('2d');

        if (this.chartInstance) {
          this.chartInstance.destroy();
        }

        this.chartInstance = new Chart(ctx, {
          type: 'bar',
          data: this.chartData,
          options: this.options,
        });
      });
    },
  },
};
</script>

<style scoped>
.chart-container {
  background: white;
  border-radius: 8px;
  padding: 1rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.chart-controls {
  display: flex;
  gap: 0.5rem;
}

.metric-card {
  background: #f8f9fa;
  border-radius: 4px;
  border: 1px solid #dee2e6;
}

.metric-value {
  font-size: 1.5rem;
  font-weight: bold;
  color: #007bff;
}

.metric-label {
  font-size: 0.875rem;
  color: #6c757d;
  margin-top: 0.25rem;
}
</style>
