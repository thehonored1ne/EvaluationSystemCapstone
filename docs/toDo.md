## Todo
- [x] fix the issue wherein even if i navigate into notification section, the numbered badge beside notifications in the sidebar is still present even if i already read it. check if the issues is just on admin side.

- [x] dropdown with long list in my app should be searchable also. check all the parts that has this dropdown with long list and make them searchable. before proceeding ask clarifying question first.

- [x] create a reusable confirmation modal. for every destructive action like delete. make sure this modal also show the item details that is being deleted. also make sure it uses the same style as my app.

- [x] in manage users. add a delete button in action column on users(deans, program heads, faculty professor, student, staff). make sure that our reusable confirmation modal goes first before deleting.

- [x] in evaluation settings, it should prevent a the admin from removing the current saved schedules if the evaluation is still open. they should close the schedule first then they can remove it safely. after doing the fix make sure you update the md files thats connected to this fix.

- [x] add a none option in department filter, so if theres a column with a value of none, i can look for them faster. also in manage students, add a program and year level filter

- [x] In reports it should include a summary report of the evaluation results and that can be downloadable to pdf. the filter should be school year, semester. lets make this way for now. the summary should show employee number, professor name, department, evaluation type averages (student, peer, self), total submissions, and overall rating average.

- [x] impliment ai pipeline (vader sentiment analysis with custom lexicon for tagalog,taglish, tfidf, decision tree classification). will use it to analyze comments from the evaluation results. before proceeding ask clarifying question first. put the python code inside folder **python/** and use flask in this. the laravel will call the python via api call. create a plan on how we will do this. make sure it will use the same database connection and models.

- a button that can automatically update the current school year and semester for users (admin,dean, program head, faculty, student, staff).

- [x] a way to train more our ai pipeline for accuracy. for example the ai says the comment is negative, but it is positive. we should be able to correct the ai and retrain it, and the ai will be able to learn from it. we should test our ai with more test data so in the future we can test its accuracy by ground truth test or other testing methods. it should create a confusion matrix to check the accuracy of the ai.

- use redis for queue jobs to improve performance.

- [x] impliment rate limiting with ip ban to our endpoints, ai endpoints etc.

- import data via excel feature (only admin can do this)

- ui issues in reports table

- need to update the summary result more, lets make a report generation that can be exported to pdf with all the data in the evaluation. create a separate table to store the evaluation results that can be used for reporting purposes. should show individual result and summary result.

- weights for calculation of overall rating should be customizable per evaluation type by admin. create a section in evaluation settings to configure this. still thinking how this applies

- [x] Evaluation type check configurations update (Peer, Upward, Downward, Self), still need through planning:

    | **Type**             | **Who Evaluates** | **Who Gets Evaluated** |
    |----------------------|-------------------|------------------------|
    | Peer Evaluation      | Same level/rank   | Same level/rank        |
    | Upward Evaluation    | Subordinate       | Superior               |
    | Downward Evaluation  | Superior          | Subordinate            |
    | Self Evaluation      | Yourself          | Yourself               |

    Dean - can evaluate all program heads(downward), can evaluate self(self)
    Program Head - can evaluate their subordinate(downward - faculty), can evaluate self(self), can evaluate superior(upward - dean)
    Faculty - can evaluate their peers(faculty - faculty), can evaluate superior(upward - program head on their department), can evaluate self(self)
    Student - can evaluate their superior(upward - faculty)
    Staff - can evaluate their superior(upward - program head), can evaluate self(self)
