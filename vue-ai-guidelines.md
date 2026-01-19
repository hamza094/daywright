# Vue 2.6 Guidelines for AI Code Assistants

This file contains Vue 2.6 coding standards optimized for AI code assistants like Claude Code, GitHub Copilot, and Cursor. These guidelines are derived from industry best practices and tailored specifically for the DayWright project management application.

## Core Vue Principle

**Follow Vue 2.6 conventions first.** If Vue has a documented way to do something, use it. Only deviate when you have a clear justification that aligns with the project's architecture.

## Vue Component Structure

### Component Organization

- Use PascalCase for component names (`ProjectPage`, `TaskModal`)
- Use kebab-case for component files (`project-page.vue`, `task-modal.vue`)
- Group related components in subdirectories (`Project/`, `Admin/`, `Authentication/`)

### Component Template Structure

```vue
<template>
  <div class="component-container">
    <!-- Template content -->
  </div>
</template>

<script>
export default {
  name: 'ComponentName',
  components: {
    /* Component imports */
  },
  props: {
    /* Props definition */
  },
  data() {
    return {
      /* Local state */
    };
  },
  computed: {
    /* Computed properties */
  },
  watch: {
    /* Watchers */
  },
  created() {
    /* Lifecycle hooks */
  },
  methods: {
    /* Methods */
  },
};
</script>
```

## Props & Data Management

### Props Definition

- Always specify prop types and defaults
- Use camelCase for prop names
- Provide validation when necessary

```javascript
props: {
  projectSlug: { type: String, required: true },
  userData: { type: Object, default: () => ({}) },
  isEditable: { type: Boolean, default: false }
}
```

### Data Properties

- Use camelCase for data properties
- Initialize all data properties in the `data()` function
- Use descriptive names that reflect the data's purpose

```javascript
data() {
  return {
    isLoading: false,
    formData: { name: '', description: '' },
    validationErrors: {},
    currentUser: null
  };
}
```

## Vuex State Management

### Store Module Structure

Follow the DayWright pattern with namespaced modules:

```javascript
const state = {
  /* State properties */
};
const mutations = {
  /* Synchronous state changes */
};
const actions = {
  /* Asynchronous operations */
};

export default { namespaced: true, state, mutations, actions };
```

### State Management Patterns

- Use `mapState`, `mapGetters`, `mapActions`, `mapMutations` for Vuex integration
- Keep mutations simple and synchronous
- Handle async operations in actions
- Use proper error handling in actions

```javascript
import { mapState, mapActions, mapMutations } from 'vuex';

export default {
  computed: {
    ...mapState('project', ['project', 'user']),
    ...mapState('task', ['tasks', 'message']),
  },
  methods: {
    ...mapActions('project', ['loadProject']),
    ...mapMutations('task', ['updateTask']),
  },
};
```

## Event Handling

### Event Naming

- Use kebab-case for custom events (`@task-created`, `@user-updated`)
- Use camelCase for method names (`handleTaskCreated`, `updateUser`)

```javascript
// Emitting events
this.$emit('task-created', taskData);

// Event bus for cross-component communication
this.$bus.emit('project-updated', projectData);
this.$bus.on('project-updated', (projectData) => {
  // Handle the event
});
```

## Form Handling

### Form Validation

- Use consistent error handling patterns
- Display validation errors clearly
- Use the `errors` object pattern from DayWright

```javascript
data() {
  return {
    form: { name: '', description: '' },
    errors: {}
  };
},
methods: {
  validateForm() {
    this.errors = {};
    if (!this.form.name.trim()) {
      this.errors.name = ['Name is required'];
    }
    return Object.keys(this.errors).length === 0;
  },

  async submitForm() {
    if (!this.validateForm()) return;
    try {
      const response = await axios.post('/api/v1/projects', this.form);
      this.$vToastify.success('Project created successfully');
    } catch (error) {
      this.handleErrorResponse(error);
    }
  }
}
```

## API Integration

### Axios Configuration

- Use the configured axios instance with interceptors
- Handle authentication tokens automatically
- Use consistent error handling

```javascript
// API calls
async fetchProject(slug) {
  try {
    const response = await axios.get(`/api/v1/projects/${slug}`);
    return response.data;
  } catch (error) {
    this.handleErrorResponse(error);
    throw error;
  }
}

// Error handling mixin
import errorHandling from '@/mixins/errorHandling';
export default { mixins: [errorHandling] };
```

## Router Integration

### Route Guards

- Use the authentication guard pattern from DayWright
- Handle route transitions properly
- Use navigation guards for protected routes

```javascript
// Route guard example
const auth = (to, from, next) => {
  const token = localStorage.getItem('token');
  if (token) return next();
  return next('/login');
};

// Navigation
this.$router.push({ name: 'ProjectPage', params: { slug: projectSlug } });
this.$router.push({
  name: 'Activities',
  params: { slug: projectSlug },
  query: { filter: 'tasks' },
});
```

## Component Communication

### Parent-Child Communication

- Use props for parent-to-child communication
- Use events for child-to-parent communication
- Use `$refs` sparingly and only when necessary

```javascript
// Parent component
<child-component
  :project-data="projectData"
  @task-created="handleTaskCreated"
/>

// Child component
props: ['projectData'],
methods: {
  createTask() {
    this.$emit('task-created', taskData);
  }
}

// Sibling Communication
// Use Vuex for complex state sharing
// Use event bus for simple cross-component communication
```

## Lifecycle Hooks

### Hook Usage

- Use `created()` for initial data fetching
- Use `mounted()` for DOM manipulation
- Use `beforeDestroy()` for cleanup

```javascript
created() {
  this.loadProject(this.$route.params.slug);
},
mounted() {
  this.setupEventListeners();
},
beforeDestroy() {
  this.removeEventListeners();
}
```

## Computed Properties

### Computed vs Methods

- Use computed properties for derived state
- Use methods for actions and operations
- Cache expensive calculations with computed properties

```javascript
computed: {
  isProjectActive() {
    return this.project.status === 'active';
  },
  activeTasks() {
    return this.tasks.filter(task => task.status === 'active');
  },
  projectProgress() {
    const completed = this.tasks.filter(task => task.completed).length;
    const total = this.tasks.length;
    return total > 0 ? (completed / total) * 100 : 0;
  }
}
```

## Watchers

### Watcher Patterns

- Use watchers for reactive data changes
- Use `immediate: true` for initial execution
- Use `deep: true` for object watching

```javascript
watch: {
  '$route.params.slug': {
    handler(newSlug) { this.loadProject(newSlug); },
    immediate: true
  },
  'form.name': {
    handler(newName) { this.validateName(newName); }
  },
  projectData: {
    handler(newData) { this.updateUI(newData); },
    deep: true
  }
}
```

## Mixins

### Mixin Usage

- Use mixins for shared functionality
- Keep mixins focused and single-purpose
- Document mixin behavior clearly

```javascript
// activityMixins.js
export default {
  data() {
    return {
      activityTypes: [
        { status: 'all', label: 'All Activities', icon: 'fa-layer-group' },
        { status: 'my', label: 'My Activities', icon: 'fa-user' },
      ],
    };
  },
  methods: {
    getActivityIcon(description) {
      // Shared logic for activity icons
    },
  },
};
```

## Modal and Panel Patterns

### Modal Usage

- Use the `vue-js-modal` pattern from DayWright
- Handle modal state properly
- Clean up on modal close

```javascript
// Opening/closing modals
this.$modal.show('task-modal');
this.$modal.hide('task-modal');
@modal-closed="handleModalClosed"

// Panel usage
const panelHandle = this.$showPanel({
  component: 'project-form',
  openOn: 'right',
  width: 540,
  disableBgClick: true,
  keepAlive: true,
  props: { /* Panel props */ }
});

panelHandle.promise.then(result => {
  // Handle panel result
});
```

## Styling Guidelines

### CSS Classes

- Use BEM methodology for CSS classes
- Use scoped styles when possible
- Follow the existing class naming patterns

```vue
<template>
  <div class="project-card">
    <div class="project-card__header">
      <h3 class="project-card__title">{{ project.name }}</h3>
    </div>
    <div class="project-card__body">
      <!-- Content -->
    </div>
  </div>
</template>

<style scoped>
.project-card {
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 1rem;
}
.project-card__header {
  margin-bottom: 1rem;
}
.project-card__title {
  font-size: 1.25rem;
  font-weight: bold;
}
</style>
```

## Documentation & Comments

- **Small comments** only to explain "why," not "what."
- **JSDoc** for complex methods: `/** @param {string} x */`.
- **Component documentation** for complex components with multiple responsibilities.

## Common Anti-Patterns to Avoid

### ❌ Direct Store Access in Components

```javascript
// Avoid this
data() {
  return {
    auth: this.$store.state.currentUser.user,
  };
}
```

**✅ Better Approach:**

```javascript
// Use mapState instead
computed: {
  ...mapState('currentUser', ['user']),
  auth() {
    return this.user;
  }
}
```

### ❌ Inconsistent Error Handling

```javascript
// Avoid scattered error handling
.catch(error => {
  console.log(error.response.data.errors);
});
```

**✅ Better Approach:**

```javascript
// Use centralized error handling
.catch(error => {
  this.handleErrorResponse(error);
});
```

### ❌ Direct DOM Manipulation in Components

```javascript
// Avoid this
document.getElementById('text').blur();
document.getElementById('focus-target').focus();
```

**✅ Better Approach:**

```javascript
// Use Vue refs
this.$refs.textInput.blur();
this.$refs.focusTarget.focus();
```

### ❌ Inconsistent API Call Patterns

```javascript
// Avoid mixing patterns
axios
  .get('/api/v1/stages')
  .then((response) => {
    this.stages = response.data;
  })
  .catch((error) => {
    console.log(error.response.data.errors);
  });
```

**✅ Better Approach:**

```javascript
// Use consistent async/await pattern
async loadStages() {
  try {
    const response = await axios.get('/api/v1/stages');
    this.stages = response.data;
  } catch (error) {
    this.handleErrorResponse(error);
  }
}
```

### ❌ Large Component Methods

```javascript
// Avoid monolithic methods
updateName() {
  this.$Progress.start();
  axios.patch(`/api/v1/projects/${this.project.slug}`, {
    name: this.projectname,
  }).then(response => {
    const { name, slug } = response.data.project;
    this.$Progress.finish();
    this.updateNameState(name, slug, response.data.message);
    this.updateUrl(slug);
  }).catch(error => {
    this.$Progress.fail();
    this.nameEdit = false;
    this.projectname = this.project.name;
    this.showError(error);
  });
}
```

**✅ Better Approach:**

```javascript
// Break into smaller, focused methods
async updateName() {
  try {
    this.$Progress.start();
    const response = await this.updateProjectName();
    this.handleUpdateSuccess(response);
  } catch (error) {
    this.handleUpdateError(error);
  }
},

async updateProjectName() {
  return axios.patch(`/api/v1/projects/${this.project.slug}`, {
    name: this.projectname,
  });
},

handleUpdateSuccess(response) {
  const { name, slug } = response.data.project;
  this.$Progress.finish();
  this.updateNameState(name, slug, response.data.message);
  this.updateUrl(slug);
},

handleUpdateError(error) {
  this.$Progress.fail();
  this.nameEdit = false;
  this.projectname = this.project.name;
  this.showError(error);
}
```

### ❌ Inconsistent State Management

```javascript
// Avoid direct state mutations
this.$store.commit('project/nameUpdate', { name, slug });
```

**✅ Better Approach:**

```javascript
// Use actions for complex state changes
this.$store.dispatch('project/updateProjectName', { name, slug });
```

### ❌ Missing Component Names

```javascript
// Avoid anonymous components
export default {
  // Missing name
};
```

**✅ Better Approach:**

```javascript
// Always include component name
export default {
  name: 'ProjectCard',
  // ...
};
```

### ❌ Inconsistent Prop Validation

```javascript
// Avoid loose prop definitions
props: ['slug', 'access'];
```

**✅ Better Approach:**

```javascript
// Use proper prop validation
props: {
  slug: {
    type: String,
    required: true
  },
  access: {
    type: Boolean,
    default: false
  }
}
```

### ❌ Hardcoded Values in Components

```javascript
// Avoid magic numbers/strings
if (this.project.score > 21) {
  return 'hot';
}
```

**✅ Better Approach:**

```javascript
// Use constants or computed properties
const HOT_SCORE_THRESHOLD = 21;

computed: {
  projectStatus() {
    return this.project.score > HOT_SCORE_THRESHOLD ? 'hot' : 'cold';
  }
}
```

### ❌ Inconsistent Event Handling

```javascript
// Avoid inconsistent event names
@click="updateName"
@click.prevent="updateName"
```

**✅ Better Approach:**

```javascript
// Be consistent with event modifiers
@click.prevent="updateName"
@click.prevent="handleNameUpdate"
```

### ❌ Missing Loading States

```javascript
// Avoid no loading feedback
async fetchData() {
  const response = await axios.get('/api/v1/data');
  this.data = response.data;
}
```

**✅ Better Approach:**

```javascript
// Always provide loading feedback
async fetchData() {
  this.isLoading = true;
  try {
    const response = await axios.get('/api/v1/data');
    this.data = response.data;
  } finally {
    this.isLoading = false;
  }
}
```

### ❌ Inconsistent Form Handling

```javascript
// Avoid scattered form logic
data() {
  return {
    form: { name: '', description: '' },
    errors: {}
  };
}
```

**✅ Better Approach:**

```javascript
// Use form mixins or composable patterns
import formMixin from '@/mixins/formMixin';

export default {
  mixins: [formMixin],
  data() {
    return {
      form: this.createForm({
        name: { value: '', rules: ['required'] },
        description: { value: '', rules: [] },
      }),
    };
  },
};
```

## Performance Improvements

### Lazy Loading Components

```javascript
// Instead of direct imports
import HeavyComponent from './HeavyComponent.vue';

// Use dynamic imports
components: {
  HeavyComponent: () => import('./HeavyComponent.vue');
}
```

### Computed Properties for Expensive Operations

```javascript
// Instead of methods for derived data
methods: {
  getActiveTasks() {
    return this.tasks.filter(task => task.status === 'active');
  }
}

// Use computed properties
computed: {
  activeTasks() {
    return this.tasks.filter(task => task.status === 'active');
  }
}
```

### Proper Key Usage

```vue
<!-- Instead of index keys -->
<div v-for="(task, index) in tasks" :key="index"></div>
```

## Performance Optimization

### Lazy Loading

- Use dynamic imports for route components
- Lazy load heavy components
- Use `v-if` instead of `v-show` for conditional rendering when appropriate

```javascript
// Lazy loading components
components: {
  HeavyComponent: () => import('./HeavyComponent.vue');
}
```

### Key Usage

- Always use `:key` with `v-for`
- Use unique, stable keys
- Avoid using array index as key when data can change

```vue
<template>
  <div v-for="task in tasks" :key="task.id" class="task-item">
    {{ task.title }}
  </div>
</template>
```

## Accessibility

### ARIA Attributes

- Use proper ARIA labels
- Ensure keyboard navigation
- Provide alt text for images

```vue
<template>
  <button @click="handleClick" :aria-label="`Edit ${project.name}`" class="btn btn-primary">
    <i class="fas fa-edit"></i>
  </button>
</template>
```

## Quick Reference

### Naming Conventions

- **Components**: PascalCase (`ProjectPage`, `TaskModal`)
- **Files**: kebab-case (`project-page.vue`, `task-modal.vue`)
- **Props**: camelCase (`projectData`, `isEditable`)
- **Events**: kebab-case (`@task-created`, `@user-updated`)
- **Methods**: camelCase (`handleTaskCreated`, `updateUser`)
- **CSS Classes**: BEM methodology (`project-card__title`)

### File Structure

- Components: `resources/js/components/`
- Store modules: `resources/js/store/`
- Mixins: `resources/js/mixins/`
- Router: `resources/js/router.js`
- Main app: `resources/js/app.js`

### Common Patterns

- Use `mapState`, `mapActions` for Vuex integration
- Use `this.$emit()` for child-to-parent communication
- Use `this.$bus.emit()` for cross-component communication
- Use `axios` for API calls with proper error handling
- Use `this.$router.push()` for navigation
- Use `this.$modal.show()` for modals
- Use `this.$showPanel()` for panels

---

_These guidelines are optimized for AI code assistants and align with the DayWright project architecture._
