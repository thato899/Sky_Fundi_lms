# Report permissions

The Reports seeder registers `reports.view`, `generate`, `update`, `review`, `approve`, `publish`, `withdraw`, `export_pdf`, `export_csv`, `manage_grading_scales`, `manage_periods`, `manage_templates`, and `manage_comments` idempotently.

Super Admin (only in explicit organization context), Organization Administrator, and Academic Administrator receive all permissions. Teacher and Tutor receive only view and permitted comment access; they do not receive generation, approval, publication, withdrawal, configuration, PDF, or CSV permissions. Learner and Guardian receive none. Policies resolve effective membership permissions and organization ownership; role names are not used for authorization.
