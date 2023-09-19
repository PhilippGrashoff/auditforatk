# auditforatk
[![codecov](https://codecov.io/gh/PhilippGrashoff/audit/branch/main/graph/badge.svg)](https://codecov.io/gh/PhilippGrashoff/auditforatk)

This is an extension for [atk4/data](https://github.com/atk4/data). It is used to create a human-readable audit of changes made to models and other actions. It is NOT meant to add the ability to undo changes.

How the final audit looks like is up to you - you can implement the rendering yourself. A sample audit of a `Country` model could look like:
```
2023-04-17
14:34 Some User       created this Country.
14:34 Some User       set "name" to "Germny".
14:34 Some User       set "iso country code" to "GER".
15:17 Another User    changed "name" to "Germany".

2023-04-21
13:13 YetAnotherUser  set "contitent" to "America".
15:19 Another User    changed "continent" to "Europe".
15:19 Another User    enabled periodic info emails for this country.
```
In this example, the last entry is not about a field change, but audits a custom action.

# Contents
* `Audit`: This class saves all data for an audit entry. For performant reading/displaying, only `created_date`, `user_name` and `rendered_message` fields are needed. However, each audit object also stores all information to re-render an audit message, e.g. if the desired output format was changed, the title of a hasOne relation was updated or for translation.
* `AuditTrait`: This Trait is added to any Model which should be audited. It sets the necessary hooks to create audits on creation, any field change and on deletion. Fields can be excluded from audit for each model.
* `AuditController`: Contains all logic how an Audit should be created. If you want other Audits than mere field audits, you need to extend this class to fit your purposes.
* `MessageRenderer`: Highly coupled with `AuditController`. It takes care of rendering a human-readable message for each Audit. You can extend this class to have a different output format, e.g. have rendered HTML in the rendered message. The result is saved in `Audit` `rendered_message` field.