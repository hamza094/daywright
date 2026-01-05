<template>
  <main class="profile-page">
    <header class="page-top">
      <nav aria-label="Breadcrumb">
        <span>
          <span class="page-top_heading">Profile </span>
          <span class="page-top_arrow"> &gt; </span>
          <span> {{ user.name }}</span>
        </span>
      </nav>
      <div v-if="owner" class="float-right">
        <button class="btn btn-primary btn-sm" @click="$modal.show('edit-profile')">Edit Profile</button>

        <FeatureDropdown :feature-pop.sync="featurePop">
          <ul v-if="owner">
            <li v-if="user.avatar" class="feature-dropdown_item-content" @click="deleteAvatar">
              <i class="fa-regular fa-user-circle"></i> Remove Avatar
            </li>

            <li class="feature-dropdown_item-content" @click.prevent="deleteProfile()">
              <i class="fa-regular fa-trash-alt"></i>Delete Profile
            </li>
          </ul>
        </FeatureDropdown>
      </div>
    </header>

    <EditProfile :user="user"></EditProfile>

    <section class="page-content" aria-label="Profile">
      <div class="row">
        <UserAvatar :user-id="user.id" :avatar="userAvatar" :name="user.name"></UserAvatar>

        <div class="col-md-10">
          <div class="content">
            <p class="content-name">{{ user.name }}</p>
            <p class="content-info" v-if="user.info">
              <template v-if="user.info.company || user.info.position">
                <span v-if="user.info.company">{{ user.info.company }}</span>
                <span class="content-dot" v-if="user.info.company && user.info.position"></span>
                <span v-if="user.info.position">{{ user.info.position }}</span>
              </template>
              <span v-else>Not Defined</span>
            </p>
          </div>
        </div>
      </div>
      <hr />
      <section aria-labelledby="profile-detail-heading">
        <p id="profile-detail-heading" class="pro-info" role="heading" aria-level="2">Profile Detail</p>
        <div class="row">
          <div class="col-md-6">
            <p class="crm-info">
              <b>Email</b>:
              <span> {{ user.email }} </span>
            </p>

            <p class="crm-info" v-if="user.info">
              <b>Mobile</b>:<span> {{ user.info.mobile ? user.info.mobile : 'Not Defined' }}</span>
            </p>

            <p class="crm-info" v-if="user.info">
              <b>Address</b>:<span> {{ user.info.address ? user.info.address : 'Not Defined' }}</span>
            </p>

            <p class="crm-info">
              <b>Created At</b>: <span> {{ user.created_at }} </span>
            </p>

            <p class="crm-info">
              <b>Updated At</b>: <span> {{ user.updated_at }} </span>
            </p>

            <p class="crm-info">
              <b>Last Seen</b>: <span> {{ user.updated_at }} </span>
            </p>
          </div>

          <div class="col-md-6">
            <p class="crm-info" v-if="user.info">
              <b>Bio</b>:<span>{{
                user.info.bio
                  ? user.info.bio
                  : 'Donec in odio eget risus placerat molestie. Etiam augue turpis, tristique nec accumsan a, vehicula vitae quam. Sed imperdiet vulputate mi in molestie. Sed lacus quam, suscipit ut velit et, commodo sagittis leo.'
              }}</span>
            </p>
            <div>
              <p class="crm-info">
                <b>Roles</b>:
                <span v-for="role in user.roles" :key="role.id || role.name"> {{ role.name }} ,</span>
              </p>
            </div>
          </div>
        </div>
      </section>
      <hr />
      <div v-if="owner">
        <TwoFactorAuth></TwoFactorAuth>
        <UserTokens></UserTokens>
        <br />
        <hr />
        <ProjectInvitation></ProjectInvitation>
      </div>
    </section>
  </main>
</template>

<script>
import EditProfile from './Edit.vue';
import UserAvatar from './Avatar.vue';
import ProjectInvitation from './ProjectInvitation.vue';
import UserTokens from './UserTokens.vue';
import TwoFactorAuth from './TwoFactorAuth.vue';
import FeatureDropdown from '../FeatureDropdown.vue';
import { mapState, mapMutations } from 'vuex';

export default {
  components: { EditProfile, UserAvatar, ProjectInvitation, FeatureDropdown, UserTokens, TwoFactorAuth },
  data() {
    return {
      auth: this.$store.state.currentUser.user.uuid,
      owner: false,
      featurePop: false,
    };
  },

  computed: {
    ...mapState('profile', ['user', 'userAvatar', 'invitations']),
  },

  created() {
    this.loadUser();
  },

  methods: {
    ...mapMutations('profile', ['updateUser', 'updateUserAvatar']),

    loadUser() {
      axios
        .get('/users/' + encodeURIComponent(this.$route.params.uuid))
        .then((response) => {
          const user = response.data.user;
          this.updateUser(user);
          this.updateUserAvatar(user.avatar);
          this.owner = this.user.uuid === this.auth;
        })
        .catch((error) => {
          this.$vToastify.warning(error?.response?.data?.message || 'Failed to load user');
        });
    },
    deleteAvatar() {
      this.sweetAlert('Yes, delete it!').then((result) => {
        if (result.value) {
          this.$vToastify.loader('Please Wait Removing Avatar');

          axios
            .patch('/users/' + this.user.id + '/avatar_remove')
            .then((response) => {
              this.$vToastify.info(response.data.message);
              this.user.avatar = null;
              this.userAvatar = null;
            })
            .catch((error) => {
              const msg = error?.response?.data?.message || error?.message || 'There was something wrong.';
              swal.fire('Failed!', msg, 'warning');
            })
            .finally(() => {
              this.$vToastify.stopLoader();
            });
        }
      });
    },

    deleteProfile() {
      this.sweetAlert('Yes, delete it!').then((result) => {
        if (result.value) {
          axios
            .delete('/users/' + this.user.id)
            .then((response) => {
              this.$vToastify.success(response.data.message);
              this.$store.dispatch('currentUser/deleteUser');
            })
            .catch((error) => {
              const msg = error?.response?.data?.message || error?.message || 'There was something wrong.';
              swal.fire('Failed!', msg, 'warning');
            });
        }
      });
    },
  },
};
</script>
