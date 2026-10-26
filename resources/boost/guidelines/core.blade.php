## Data Health

`data-health/data-health` tracks application-specific data problems as findings linked
to Eloquent models. Activate the `data-health-development` skill before generating
findings, scanning an application for inconsistencies, or changing finding logic.

- Establish each rule from application code, including legitimate exceptions and a
  possible correction. Column names or nullability alone do not establish a problem.
- Prefer one class extending `DataHealth\Finding` directly. Detection reports each
  occurrence with `found()` and ordinarily returns `null`.
- Verification rechecks one occurrence's model and context without changing business
  data or rerunning detection. Resolution applies a known correction using application logic.
- Assess resolution for every finding. Implement it when deterministic and justified;
  otherwise explain the judgment required. Implementing it does not enable automatic execution.
- The manager marks a record resolved on `true`; `false` leaves status unchanged.
  Throw for invalid input or an unreliable result. Replace generated placeholders before use.

Detailed guidance: `vendor/data-health/data-health/resources/boost/skills/data-health-development/SKILL.md`.
