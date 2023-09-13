# auditforatk

This is an extension for  [atk4/data](https://github.com/atk4/data). It is used to create a human-readable audit of changes made to models and other actions. It is NOT meant to add the ability to undo changes.

How the final audit looks like is up to you - you can implement the rendering yourself. A sample audit of a `Country` model could look like:
```
2023-04-17
14:34 Some User     created this Country.
14:34 Some User     set "name" to "Germny".
14:34 Some User     set "iso country code" to "GER".
15:17 Another User  changed "name" to "Germany".

2023-04-21
15:19 Another User  enabled periodic info emails for this country.
```
In this example, the last entry is not about a field change, but audits a custom action.