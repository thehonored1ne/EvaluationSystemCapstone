# Roles, Permissions & Evaluation Boundaries

This document maps user roles to their features, views, and target evaluation boundaries.

---

## 1. System Roles
* **Admin**: Complete system dashboard access, CRUD controls for users/departments/programs/schedules, evaluation questions, and global analytics.
* **Dean**: Department-wide monitoring of evaluation participation rates, reports generation, evaluates Academic Faculty, Program Heads & Department Heads.
* **Program Head**: Program-specific monitoring of classes, student enrollment, evaluates Faculty Professors, evaluates Self, evaluates Dean.
* **Department Head**: Administrative department monitoring, evaluates Department Staff, evaluates Self, evaluates Dean.
* **Faculty**: Accesses self-evaluations, peer evaluations, and supervisor evaluations (targets Program Head).
* **Student**: Accesses evaluations targeting teachers of classes they are currently enrolled in.
* **Staff**: Accesses self-evaluations, peer evaluations, and supervisor evaluations (targets Department Head).

---

## 2. Evaluation Matrix
The table below displays who evaluates whom under different evaluation configurations:

| **Evaluation Type** | **Evaluator Role** | **Target (Evaluatee) Role** | **Department boundaries** |
|:---|:---|:---|:---|
| **Student Evaluation** | Student | Faculty | Must be enrolled in the teacher's active class. |
| **Dean Evaluation** | Dean | Faculty / Program Head / Dept Head | Evaluates Faculty and Program Heads within their college. |
| **Program / Dept Head Eval** | Program Head / Department Head | Faculty / Staff | Program Head evaluates Faculty; Department Head evaluates Department Staff. |
| **Peer Evaluation** | Faculty / Staff | Peer Faculty / Peer Staff | Faculty evaluates Peer Faculty; Staff evaluates Peer Staff in the same department. |
| **Self Evaluation** | Any Employee | Themselves | Program Head, Department Head, Dean, Faculty, Staff evaluate Self. |
| **Superior Evaluation** | Subordinate | Superior | Faculty → Program Head, Staff → Department Head, Program Head / Department Head → Dean. |

---

## 3. Route Guard & Access Control Policies
* **Spatie Middleware**: Middleware directives (`role:admin`, `role:dean`, etc.) guard web routes.
* **Livewire Component Authorization**: Actions on Livewire controllers (such as deleting users or ending active evaluation schedules) perform backend policy checks (`$user->hasRole(...)`) prior to execution to prevent request forgery.
* **Self-Account Safety Policy**: Backend guards in `toggleActive()`, `confirmDelete()`, and `deleteUser()` prevent active logged-in administrators (`auth()->id()`) from self-disabling or deleting their own accounts.
* **System Administrator Protection**: Guard prevents disabling or deleting the last active Administrator account to ensure system accessibility.
