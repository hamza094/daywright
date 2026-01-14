/** @type {import('stylelint').Config} */
export default {
  extends: ["stylelint-config-standard-scss"],
  rules:{
    // Allows kebab-case (foo-bar) AND snake_case (foo_bar) 
    // AND mixed patterns (foo-bar_baz)
    "selector-class-pattern": [
      "^([a-z][a-z0-9]*)([-_][a-z0-9]+)*$",
      {
        "message": "Expected class selector to be lowercase with hyphens or underscores (e.g., .my-class or .my_class)",
        "resolveNestedSelectors": true
      }
    ],
    "no-descending-specificity": null,
  }
};