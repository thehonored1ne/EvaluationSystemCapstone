# Roles, Permissions & Evaluation Boundaries

This document maps user roles to their features, views, and target evaluation boundaries.

---

## 1. System Roles
* **Admin**: Complete system dashboard access, CRUD controls for users/departments/programs/schedules, evaluation questions, and global analytics.
* **Dean**: Department-wide monitoring of evaluation participation rates, reports generation, and evaluator roles.
* **Program Head**: Program-specific monitoring of classes, student enrollment, faculty evaluations, and reports generation.
* **Faculty**: Accesses self-evaluations, peer evaluations, and supervisor evaluations.
* **Student**: Accesses evaluations targeting teachers of classes they are currently enrolled in.
* **Staff**: Accesses self-evaluations and supervisor evaluations.

---

## 2. Evaluation Matrix
The table below displays who evaluates whom under different evaluation configurations:

| **Evaluation Type** | **Evaluator Role** | **Target (Evaluatee) Role** | **Department boundaries** |
|:---|:---|:---|:---|
| **Student Evaluation** | Student | Faculty | Must be enrolled in the teacher's active class. |
| **Peer Evaluation** | Faculty | Faculty | Must belong to the *same* department. |
| **Downward Evaluation** | Dean / Program Head | Program Head / Faculty | Must belong to the *same* department (subordinates). |
| **Upward Evaluation** | Faculty / Staff | Program Head | Must belong to the *same* department (superiors). |
| **Self Evaluation** | Any Employee | Themselves | Evaluates their own performance. |

---

## 3. Route Guard & Access Control Policies
* **Spatie Middleware**: Middleware directives (`role:admin`, `role:dean`, etc.) guard web routes.
* **Livewire Component Authorization**: Actions on Livewire controllers (such as deleting users or ending active evaluation schedules) perform backend policy checks (`$user->hasRole(...)`) prior to execution to prevent request forgery.
* **Self-Account Safety Policy**: Backend guards in `toggleActive()`, `confirmDelete()`, and `deleteUser()` prevent active logged-in administrators (`auth()->id()`) from self-disabling or deleting their own accounts.
* **System Administrator Protection**: Guard prevents disabling or deleting the last active Administrator account to ensure system accessibility.
