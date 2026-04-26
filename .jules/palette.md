# Palette's Journal - Leopardo RH

This journal contains critical UX and accessibility learnings for the Leopardo RH project.

## 2025-05-14 - Initial Setup
**Learning:** Started exploring the mobile application.
**Action:** Investigating login and core features for micro-UX improvements.

## 2025-05-14 - Flutter Accessibility & Micro-UX
**Learning:** In Flutter, `tooltip` is the primary way to provide accessibility labels for `IconButton` widgets. Without it, screen readers only identify the element as a "Button".
**Action:** Always include a descriptive `tooltip` for icon-only buttons to ensure a11y compliance.

## 2026-04-22 - Loading State Accessibility
**Learning:** `CircularProgressIndicator` doesn't provide feedback to screen readers by default.
**Action:** Wrap loading indicators in `Semantics` widgets with a descriptive `label` (e.g., 'Connexion en cours...') to inform users that an action is being processed. Avoid `const` on `Semantics` if the child or label might be dynamic, and watch for "const_with_non_const" analyzer errors.

## 2026-05-22 - Pull-to-refresh for empty states
**Learning:** To keep the `RefreshIndicator` functional in Flutter when displaying empty or error states, the content MUST be wrapped in a scrollable widget (e.g., `ListView`) with its physics set to `AlwaysScrollableScrollPhysics`.
**Action:** Use `ListView` with `AlwaysScrollableScrollPhysics` when implementing pull-to-refresh on screens that might have empty states or errors.

## 2026-05-22 - CircularProgressIndicator Accessibility
**Learning:** In Flutter, `CircularProgressIndicator` has a `semanticsLabel` property. Using it is cleaner and more concise than wrapping it in a `Semantics` widget.
**Action:** Prefer using `semanticsLabel` directly on `CircularProgressIndicator` for providing accessibility feedback.
