## Todo

- [X] Implement initial-load lazy-loading (`#[Lazy]`) and premium shimmer skeleton loaders across all remaining admin-facing pages (Deans, Program Heads, Staff, Subjects, Classes, AI Sentiment, Questions, Settings, Evaluations, Results, and Analytics).
- [X] fix the issue wherein even if i navigate into notification section, the numbered badge beside notifications in the sidebar is still present even if i already read it. check if the issues is just on admin side.
- [X] dropdown with long list in my app should be searchable also. check all the parts that has this dropdown with long list and make them searchable. before proceeding ask clarifying question first.
- [X] create a reusable confirmation modal. for every destructive action like delete. make sure this modal also show the item details that is being deleted. also make sure it uses the same style as my app.
- [X] in manage users. add a delete button in action column on users(deans, program heads, faculty professor, student, staff). make sure that our reusable confirmation modal goes first before deleting.
- [X] in evaluation settings, it should prevent a the admin from removing the current saved schedules if the evaluation is still open. they should close the schedule first then they can remove it safely. after doing the fix make sure you update the md files thats connected to this fix.
- [X] add a none option in department filter, so if theres a column with a value of none, i can look for them faster. also in manage students, add a program and year level filter
- [X] In reports it should include a summary report of the evaluation results and that can be downloadable to pdf. the filter should be school year, semester. lets make this way for now. the summary should show employee number, professor name, department, evaluation type averages (student, peer, self), total submissions, and overall rating average.
- [X] impliment ai pipeline (vader sentiment analysis with custom lexicon for tagalog,taglish, tfidf, decision tree classification). will use it to analyze comments from the evaluation results. before proceeding ask clarifying question first. put the python code inside folder **python/** and use flask in this. the laravel will call the python via api call. create a plan on how we will do this. make sure it will use the same database connection and models.

- a button that can automatically update the current school year and semester for users (admin,dean, program head, faculty, student, staff).

- [X] a way to train more our ai pipeline for accuracy. for example the ai says the comment is negative, but it is positive. we should be able to correct the ai and retrain it, and the ai will be able to learn from it. we should test our ai with more test data so in the future we can test its accuracy by ground truth test or other testing methods. it should create a confusion matrix to check the accuracy of the ai.

- use redis for queue jobs to improve performance.

- [X] impliment rate limiting with ip ban to our endpoints, ai endpoints etc.
- [X] Single-Question Interactive Evaluation Wizard (`evaluation-form.blade.php`), Collapsed Mini Sidebar with GRC logo, dark red navbar & active page styling (`#800000`), role badge, notification count badge, and persistent dark mode across full page reloads & SPA transitions (`$flux.appearance`).
- [X] Redesigned Admin Reports Page (`/admin/reports`): Eliminated traditional tables in both Summary and Individual reports, replacing them with Executive Metric Cards, Criteria Performance Progress Bars, AI Sentiment & Insights Blocks (sentiment breakdown bar + positive/neutral/constructive percentages + executive summary text), Submitted Comments Stream cards, and Faculty Performance Grid Cards with full `window.print()` single-page export support.
- [X] Evaluation Form Draft Persistence & Required Comments: Added `localStorage` draft saving in Alpine (`x-data`) for 1-5 rating answers, comments, and question step across page reloads & dashboard navigation. Enforced `required|string|min:3` comments validation on submit with UI red asterisk `Comments & Suggestions *` and error alert. Updated progress bar line fill to `bg-amber-400 dark:bg-amber-400`.
- [X] Evaluator Navbar, Footer & Table Cleanliness: Enabled navbar and footer for all logged-in evaluator roles (`@if(auth()->check())`), fixed notification badge positioning, auto-hid dashboard header banner when evaluation form is open, and cleaned up table cells in all 5 evaluator dashboards to display strictly single-line strings under Name and Subject headers.

- need to update the summary result more, lets make a report generation that can be exported to pdf with all the data in the evaluation. create a separate table to store the evaluation results that can be used for reporting purposes. should show individual result and summary result.
- weights for calculation of overall rating should be customizable per evaluation type by admin. create a section in evaluation settings to configure this. still thinking how this applies

- [X] Evaluation type check configurations update (Peer, Upward, Downward, Self), still need through planning:

  | **Type**      | **Who Evaluates** | **Who Gets Evaluated** |
  | ------------------- | ----------------------- | ---------------------------- |
  | Peer Evaluation     | Same level/rank         | Same level/rank              |
  | Upward Evaluation   | Subordinate             | Superior                     |
  | Downward Evaluation | Superior                | Subordinate                  |
  | Self Evaluation     | Yourself                | Yourself                     |

  Dean - can evaluate all program heads(downward), can evaluate self(self)
  Program Head - can evaluate their subordinate(downward - faculty), can evaluate self(self), can evaluate superior(upward - dean)
  Faculty - can evaluate their peers(faculty - faculty), can evaluate superior(upward - program head on their department), can evaluate self(self)
  Student - can evaluate their superior(upward - faculty)
  Staff - can evaluate their superior(upward - program head), can evaluate self(self)

- export/import/template in employees and student data


# **New todo:** - [X] Completed

- [X] **Evaluation Settings Overhaul & Department Leadership Sync (2026-08-14)**:
  - Added Dean Evaluation Parts (`dean`) and Superior Evaluation Parts (`superior`) to Section 4 in `evaluation-settings.blade.php`.
  - Upgraded contrast and legibility across Section 3 & Section 4 for both Light Mode and Dark Mode.
  - Standardized the 6 relationship categories across the system (Student, Dean, Program/Dept Head, Peer, Self, Superior) and updated labels to explicitly list `PH/DH → Dean`.
  - Implemented bidirectional department leadership synchronization (`syncDepartmentHeadship()`) between Employees and Departments pages, fixing duplicate Program Head display bugs and unassign persistence issues.

[X] [Relationships check and new label]

Student Evaluation: student evaluates faculty professor

Dean Evaluation: dean evaluates program head, dean evaluates department head

Program / Department Head Evaluation: program head evaluates faculty professor, department head evaluates department staff

Peer Evaluation: faculty prof evaluates peer faculty prof, department staff evaluates peer department staff

Self Evaluation: program head  evaluates self, department head evaluates self, dean evaluates self, faculty professor evaluates self, department staff evaluates self.

Superior Evaluation: program head evaluates dean, department head evaluates dean, faculty professor evaluates program head, department staff evaluates department head.

[Example Computation for each part in student evaluation]

Part 1:
given example: part max pts: 36

  Rating | max rating      Points

1. 5           5             ?
2. 4           5             ?
3. 3           5             ?
4. 4           5             ?
5. 5           5             ?
6. 5           5             ?

total question: 6

formula:
Rating / Max Rating * Total Question = Points

example:

1. 5 / 5 * 6 = 6 pts
2. 4 / 5 * 6 = 4.8 pts
3. 3 / 5 * 6 = 3.6 pts
4. 4 / 5 * 6 = 4.8 pts
5. 5 / 5 * 6 = 6 pts
6. 5 / 5 * 6 = 6 pts

Formula for total points:

31.2 = 6 + 4.8 + 3.6 + 4.8 + 6 + 6

part 1 points: 31.2

[Example Computation for combining all part in student evaluation with weights applied]

given example:
total max points: 90
weighted rating: 45%
for example student evaluation have 3 parts

formula:

part 1 + part 2 + part 3 = total part points

ex. 31.2 + 31.2 + 16.2 = 78.6

formula:
total part points / max points * 100 * weighted rating = weighted score

ex. 78.6 / 90 * 100 * .45 = 39.3

weighted score: 39.3%

[Example formula for max pts]
Note: total max pts set and weights is needed to by dynamic. it means the admin can change it anytime.
the total max pts should be rounded

given:
total max pts set: 200 pts
total weights: 100%

distribution:

student evaluation:(30% weight)
dean evaluation:(15% weight)
ph/dh evaluation:(15% weight)
peer evaluation:(15% weight)
self evaluation: (5% weight)
superior evaluation: (20% weight)

example:

student evaluation: 30% of 200 is 60 pts, so student total max point now is 60 pts.
dean evaluation: 15% of 200 is 30 pts.
ph/dh evaluation: 15% of 200 is 30 pts.
peer evaluation: 15% of 200 is 30 pts.
self evaluation: 5% of 200 is 10 pts.
superior evaluation: 20% of 200 is 40 pts.

[when does formula apply]

1.Per-form computation (happens when an evaluation is submitted)
2.Weighted conversion
3.Final score computation
