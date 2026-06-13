## Todo
- [x] fix the issue wherein even if i navigate into notification section, the numbered badge beside notifications in the sidebar is still present even if i already read it. check if the issues is just on admin side.

- [x] dropdown with long list in my app should be searchable also. check all the parts that has this dropdown with long list and make them searchable. before proceeding ask clarifying question first.

- [x] create a reusable confirmation modal. for every destructive action like delete. make sure this modal also show the item details that is being deleted. also make sure it uses the same style as my app.

- [x] in manage users. add a delete button in action column on users(deans, program heads, faculty professor, student, staff). make sure that our reusable confirmation modal goes first before deleting.

- [x] in evaluation settings, it should prevent a the admin from removing the current saved schedules if the evaluation is still open. they should close the schedule first then they can remove it safely. after doing the fix make sure you update the md files thats connected to this fix.

- [x] add a none option in department filter, so if theres a column with a value of none, i can look for them faster. also in manage students, add a program and year level filter

- In reports it should include a summary report of the evaluation results and that can be downloadable to pdf. the filter should be school year, semester. lets make this way for now. the summary should show 

- [x] impliment ai pipeline (vader sentiment analysis with custom lexicon for tagalog,taglish, tfidf, decision tree classification). will use it to analyze comments from the evaluation results. before proceeding ask clarifying question first. put the python code inside folder **python/** and use flask in this. the laravel will call the python via api call. create a plan on how we will do this. make sure it will use the same database connection and models.

- a button that can automatically update the current school year and semester.

- use redis for queue jobs

- impliment rate limiting with ip ban to our endpoints, ai endpoints etc.

- import data via excel feature

- Evaluation type check configurations update (Peer, Upward, Downward, Self), still need through planning:

    | **Type**             | **Who Evaluates** | **Who Gets Evaluated** |
    |----------------------|-------------------|------------------------|
    | Peer Evaluation      | Same level/rank   | Same level/rank        |
    | Upward Evaluation    | Subordinate       | Superior               |
    | Downward Evaluation  | Superior          | Subordinate            |
    | Self Evaluation      | Yourself          | Yourself               |
