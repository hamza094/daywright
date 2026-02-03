# Vue 2.7 Guidelines for AI Code Assistants

This document is a compact, review-friendly standard for DayWright’s Vue 2.7 code. It focuses on the rules we actually enforce.

## Core Principle

Follow Vue 2.7 conventions first. Prefer Composition API in new code to ease future migration to Vue 3.

---

## 1. Naming & Structure

- Components: PascalCase component names, kebab-case filenames.
- Group related components by feature (Project/, Admin/, Authentication/).
- Always include a component `name`.

## 2. Composition API Preference

- New components should use the Composition API when practical.
- Existing Options API components can remain as-is; avoid refactors unless needed.
- Keep composables small and feature-focused; co-locate with the feature if not shared.

## 3. Props, State, and Derived Data

- Always define prop types and defaults; prefer strict validation.
- Initialize all local state in `data()` (Options API) or `setup()` (Composition API).
- Use computed properties for derived state; keep methods for actions.

## 4. Events & Communication

- Custom events: kebab-case (`task-created`).
- Methods: camelCase (`handleTaskCreated`).
- Parent → child: props. Child → parent: events. Use Vuex for shared state.
- Use `$refs` sparingly and only for focus/imperative DOM needs.

## 5. Vuex & State Management

- Use namespaced modules.
- Mutations are synchronous; async work belongs in actions.
- Use `mapState`, `mapGetters`, `mapActions`, `mapMutations` consistently.

## 6. API & Error Handling

- Use the configured axios instance with interceptors.
- Use async/await consistently.
- Centralize error handling via mixins/helpers; avoid ad‑hoc `console.log`.

## 7. Routing

- Use project route guards for protected routes.
- Prefer named routes and explicit params/queries.

## 8. Performance Basics

- Use dynamic imports for heavy or rarely used components.
- Always use stable `:key` values for `v-for` (never array index if data can change).

## 10. Anti‑Patterns to Avoid

- Direct store access in `data()`; use `mapState` instead.
- Direct DOM manipulation; use Vue refs.
- Large monolithic methods; break into smaller helpers.
- Inconsistent API call patterns; standardize on async/await.
- Missing loading states for async operations.

---

## Quick Reference

### Naming Conventions

- Components: PascalCase (`ProjectPage`)
- Files: kebab-case (`project-page.vue`)
- Props: camelCase (`projectData`)
- Events: kebab-case (`task-created`)
- Methods: camelCase (`handleTaskCreated`)
- CSS: BEM (`project-card__title`)

### File Structure

- Components: `resources/js/components/`
- Store: `resources/js/store/`
- Mixins: `resources/js/mixins/`
- Router: `resources/js/router.js`
- Main app: `resources/js/app.js`
