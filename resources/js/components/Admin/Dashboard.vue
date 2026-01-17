<template>
  <div>
    <div class="page-top mb-4">Welcome To Your Dashboard</div>
    <div class="container" v-if="admin.access">
      <div class="row">
        <div class="col-md-4">
          <router-link :to="{ name: 'ProjectPanel' }" class="admin-dashboard-link">
            <div class="card">
              <div class="ribbon ribbon-top bg-blue">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="icon"
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  stroke-width="2"
                  stroke="currentColor"
                  fill="none"
                  stroke-linecap="round"
                  stroke-linejoin="round">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                  <path
                    d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"></path>
                </svg>
              </div>
              <div class="card-body">
                <h3 class="card-title">Projects</h3>
                <p class="text-secondary">
                  Empower administrators with comprehensive project control, offering insights, filtering options, and
                  member management capabilities.It's where you can keep an eagle eye on projects,
                </p>
              </div>
            </div>
          </router-link>
        </div>
        <div class="col-md-4">
          <router-link :to="{ name: 'TaskPanel' }" class="admin-dashboard-link">
            <div class="card">
              <div class="ribbon ribbon-top bg-yellow">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="icon icon-tabler icon-tabler-list-tree"
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  stroke-width="2"
                  stroke="currentColor"
                  fill="none"
                  stroke-linecap="round"
                  stroke-linejoin="round">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                  <path d="M9 6h11"></path>
                  <path d="M12 12h8"></path>
                  <path d="M15 18h5"></path>
                  <path d="M5 6v.01"></path>
                  <path d="M8 12v.01"></path>
                  <path d="M11 18v.01"></path>
                </svg>
              </div>
              <div class="card-body">
                <h3 class="card-title">Tasks</h3>
                <p class="text-secondary">
                  The Tasks section enables granular task management. Admins can oversee, edit, and optimize individual
                  tasks, improving project productivity.
                </p>
              </div>
            </div>
          </router-link>
        </div>
        <div class="col-md-4">
          <router-link :to="{ name: 'UserPanel' }" class="admin-dashboard-link">
            <div class="card">
              <div class="ribbon ribbon-top bg-green">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="icon icon-tabler icon-tabler-users"
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  stroke-width="2"
                  stroke="currentColor"
                  fill="none"
                  stroke-linecap="round"
                  stroke-linejoin="round">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                  <path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"></path>
                  <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                  <path d="M21 21v-2a4 4 0 0 0 -3 -3.85"></path>
                </svg>
              </div>
              <div class="card-body">
                <h3 class="card-title">Users</h3>
                <p class="text-secondary">
                  The Users section manages user accounts, roles, permissions, and login options, maintaining a secure
                  and organized user environment.
                </p>
              </div>
            </div>
          </router-link>
        </div>
      </div>

      <div class="row row-cards mt-4">
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-danger text-white avatar">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="icon icon-tabler icon-tabler-tilt-shift"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      stroke-width="2"
                      stroke="currentColor"
                      fill="none"
                      stroke-linecap="round"
                      stroke-linejoin="round">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                      <path d="M8.56 3.69a9 9 0 0 0 -2.92 1.95"></path>
                      <path d="M3.69 8.56a9 9 0 0 0 -.69 3.44"></path>
                      <path d="M3.69 15.44a9 9 0 0 0 1.95 2.92"></path>
                      <path d="M8.56 20.31a9 9 0 0 0 3.44 .69"></path>
                      <path d="M15.44 20.31a9 9 0 0 0 2.92 -1.95"></path>
                      <path d="M20.31 15.44a9 9 0 0 0 .69 -3.44"></path>
                      <path d="M20.31 8.56a9 9 0 0 0 -1.95 -2.92"></path>
                      <path d="M15.44 3.69a9 9 0 0 0 -3.44 -.69"></path>
                      <path d="M12 12m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                    </svg>
                  </span>
                </div>
                <div class="col">
                  <div class="font-weight-medium">
                    <span class="text-secondary"><b>Site Logs Viewer</b></span>
                  </div>
                  <div class="text-secondary">
                    <a
                      href="/log-viewer"
                      class="btn btn-outline-danger w-100 btn-sm"
                      target="_blank"
                      rel="noopener noreferrer"
                      >View</a
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-green text-white avatar">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="icon icon-tabler icon-tabler-24-hours"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      stroke-width="2"
                      stroke="currentColor"
                      fill="none"
                      stroke-linecap="round"
                      stroke-linejoin="round">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                      <path d="M4 13c.325 2.532 1.881 4.781 4 6"></path>
                      <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2"></path>
                      <path d="M4 5v4h4"></path>
                      <path d="M12 15h2a1 1 0 0 1 1 1v1a1 1 0 0 1 -1 1h-1a1 1 0 0 0 -1 1v1a1 1 0 0 0 1 1h2"></path>
                      <path d="M18 15v2a1 1 0 0 0 1 1h1"></path>
                      <path d="M21 15v6"></path>
                    </svg>
                  </span>
                </div>
                <div class="col">
                  <div class="font-weight-medium">
                    <span class="text-secondary"><b>Database Backup</b></span>
                  </div>
                  <div class="text-secondary">
                    <button
                      href=""
                      class="btn btn-outline-success w-100 btn-sm"
                      target="_blank"
                      @click.prevent="runBackup()">
                      Run Backup
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-twitter text-white avatar">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="icon icon-tabler icon-tabler-align-right"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      stroke-width="2"
                      stroke="currentColor"
                      fill="none"
                      stroke-linecap="round"
                      stroke-linejoin="round">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                      <path d="M4 6l16 0"></path>
                      <path d="M10 12l10 0"></path>
                      <path d="M6 18l14 0"></path>
                    </svg>
                  </span>
                </div>
                <div class="col">
                  <div class="font-weight-medium">
                    <span class="text-secondary"><b>Stages Panel</b></span>
                  </div>
                  <Stage></Stage>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-dark text-white avatar">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="icon icon-tabler icon-tabler-brand-redux"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      stroke-width="2"
                      stroke="currentColor"
                      fill="none"
                      stroke-linecap="round"
                      stroke-linejoin="round">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path
                        d="M16.54 7c-.805 -2.365 -2.536 -4 -4.54 -4c-2.774 0 -5.023 2.632 -5.023 6.496c0 1.956 1.582 4.727 2.512 6" />
                      <path
                        d="M4.711 11.979c-1.656 1.877 -2.214 4.185 -1.211 5.911c1.387 2.39 5.138 2.831 8.501 .9c1.703 -.979 2.875 -3.362 3.516 -4.798" />
                      <path
                        d="M15.014 19.99c2.511 0 4.523 -.438 5.487 -2.1c1.387 -2.39 -.215 -5.893 -3.579 -7.824c-1.702 -.979 -4.357 -1.235 -5.927 -1.07" />
                      <path
                        d="M10.493 9.862c.48 .276 1.095 .112 1.372 -.366a1 1 0 0 0 -.367 -1.365a1.007 1.007 0 0 0 -1.373 .366a1 1 0 0 0 .368 1.365z" />
                      <path d="M9.5 15.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                      <path d="M15.5 14m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    </svg>
                  </span>
                </div>
                <div class="col">
                  <div class="font-weight-medium">
                    <span class="text-secondary"><b>Task Statuses</b></span>
                  </div>
                  <TaskStatus></TaskStatus>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-5">
        <div class="col-md-6">
          <div class="card" style="height: 28rem">
            <div class="card-header">
              <div class="card-activity">Activities</div>
            </div>
            <div class="card-body card-body-scrollable card-body-scrollable-shadow">
              <div class="divide-y">
                <div
                  v-for="activity in activities"
                  :key="activity.id || (activity.user && activity.user.id) || activity.time">
                  <div class="row">
                    <div class="col-auto">
                      <span class="avatar">
                        <router-link :to="{ name: 'Profile', params: { uuid: activity.user.uuid } }">
                          <img class="avatar" :src="activity.user.avatar" alt="Avatar" />
                        </router-link>
                      </span>
                    </div>
                    <div class="col">
                      <div class="">
                        <strong>{{ activity.user.name }}</strong> {{ activity.description }}
                        <span v-if="activity.project !== null">
                          <strong>
                            <router-link :to="{ name: 'ProjectPage', params: { slug: activity.project.slug } }">{{
                              activity.project.name
                            }}</router-link>
                          </strong>
                          <span v-if="activity.project.state === 'active'" class="badge bg-success">Active</span>
                          <span v-if="activity.project.state === 'trashed'" class="badge bg-warning text-dark"
                            >Trashed</span
                          >
                        </span>
                      </div>
                      <div class="text-secondary">{{ activity.time }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6 mt5">
          <Chart></Chart>
        </div>
      </div>

      <div class="card mt-5">
        <div class="card-header">
          <h3 class="card-title">Invoices</h3>
        </div>
        <div class="table-responsive">
          <table class="table card-table table-vcenter text-nowrap datatable">
            <thead>
              <tr>
                <th>User Id</th>
                <th>Email</th>
                <th>Signup Date</th>
                <th>Last Payment Amount</th>
                <th>Last Payment Date</th>
                <th>Next Payment Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="subscription in subscriptions" :key="subscription.userId + '-' + subscription.nextPaymentDate">
                <td>
                  <span class="text-secondary">{{ subscription.userId }}</span>
                </td>
                <td>
                  <a href="invoice.html" class="text-reset" tabindex="-1">{{ subscription.email }}</a>
                </td>
                <td>{{ subscription.signUpDate }}</td>
                <td>{{ subscription.lastPaymentAmount }}{{ subscription.lastPaymentCurrency }}</td>
                <td>{{ subscription.lastPaymentDate }}</td>
                <td>{{ subscription.nextPaymentDate }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-footer d-flex align-items-center">
          <p class="m-0 text-secondary">
            Showing <span>1</span> to <span>10 </span>
            <a
              href="https://sandbox-vendors.paddle.com/subscriptions/customers"
              target="_blank"
              rel="noopener noreferrer"
              >For in-depth analysis, check the Paddle subscriptions dashboard.</a
            >
          </p>
          <ul class="pagination m-0 ms-auto"></ul>
        </div>
      </div>
    </div>
    <div v-else>
      <h3 class="text-center">Access to this area is restricted to the admin only</h3>
    </div>
  </div>
</template>
<script>
import Stage from './Stage.vue';
import TaskStatus from './TaskStatus.vue';
import Chart from './Chart.vue';
import { admin } from '../../auth';

export default {
  components: { Stage, TaskStatus, Chart },
  data() {
    return {
      activities: [],
      subscriptions: [],
      auth: this.$store.state.currentUser.user,
    };
  },
  computed: {
    admin() {
      const { access } = admin(this.auth.isAdmin);
      return { access };
    },
  },

  mounted() {
    //this.listenForActivities();
    this.loadActivities();
    this.subscriptionList();
  },
  methods: {
    runBackup() {
      axios
        .get('/admin/subscriptions/list')
        .then((response) => {})
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },
    subscriptionList() {
      axios
        .get('/admin/subscriptions/list')
        .then((response) => {
          this.subscriptions = response.data.data;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },
    loadActivities() {
      axios
        .get('/admin/dashboard/activities')
        .then((response) => {
          this.activities = response.data;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },
    /*listenForActivities() {
      Echo.channel('activities')
        .listen('DashboardActivity', (e) => {
          this.activities.unshift(e);
      });
    },*/
  },
};
</script>
