## Todo
- [x] fix the issue wherein even if i navigate into notification section, the numbered badge beside notifications in the sidebar is still present even if i already read it. check if the issues is just on admin side.

- [x] dropdown with long list in my app should be searchable also. check all the parts that has this dropdown with long list and make them searchable. before proceeding ask clarifying question first.

- [x] create a reusable confirmation modal. for every destructive action like delete. make sure this modal also show the item details that is being deleted. also make sure it uses the same style as my app.

- [x] in manage users. add a delete button in action column on users(deans, program heads, faculty professor, student, staff). make sure that our reusable confirmation modal goes first before deleting.

- Preventing schedule removal when evaluations are open.

- [x] add a none option in department filter, so if theres a column with a value of none, i can look for them faster. also in manage students, add a program and year level filter

- reports should be a summary report for the evaluation results(results depends on role)

- impliment ai pipeline (vader sentiment analysis with custom lexicon for tagalog,taglish, tfidf, decision tree classification). will use it to analyze comments from the evaluation results. before proceeding ask clarifying question first. put the python code inside folder **python/** the laravel will call the python via api call. create a plan on how we will do this. 

- update semester

- Evaluation type check configurations (Peer, Upward, Downward, Self):
    Type                   Who Evaluates                  Who Gets Evaluated
    Peer Evaluation        Same level/rank                Same level/rank
    Upward Evaluation      Subordinate                    Superior
    Downward Evaluation    Superior                       Subordinate
    Self Evaluation        Yourself                       Yourself
