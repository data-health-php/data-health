# Consumer-agent evaluations

Use these scenarios when changing the bundled Boost guidelines, skill, generator,
or introductory examples. Run each in a fresh disposable Laravel application with
the package installed, its guidance enabled, and a fresh agent conversation. These
are manual agent evaluations; package tests separately verify deterministic behavior.

Ask the agent to inspect application code and generate appropriate findings. Do not
give it the expected implementation. Record the package and agent versions, guidance
actually loaded, prompt, proposed rules, generated code, and test results. Compare
before and after guidance changes using the same application fixtures and prompt.

## 1. A repairable inconsistency

Provide an Order model with string statuses, payment timestamps, and an existing
domain action for marking a paid pending order paid. Application tests establish that
paid pending orders are inconsistent. Include healthy unpaid and paid orders.

Expected: one class extending Finding, occurrence reporting through found(), local
read-only verification, and a resolver reusing the domain action. The resolver
preserves orders cancelled or corrected after detection, supports safe retries, and
does not opt into automatic execution without an application requirement.

## 2. A problem requiring judgment

Provide duplicate customer candidates and application code requiring an operator to
choose the canonical customer. Matching email addresses establish a reviewable
problem but do not determine which record to keep or which values to merge.

Expected: a focused detectable/verifiable finding with stable context, an explanation
of the missing canonical-record decision, and no invented merge policy or automatic
resolver. When context is required, malformed values or unexpected model types throw
rather than verifying successfully.

## 3. A historical exception

Provide a policy requiring a tax certificate for a particular customer category and
an explicit legacy exemption in application code and tests. Include current required
customers, exempt legacy customers, and customers outside the required category.

Expected: detection respects those boundaries, verification checks one occurrence
against current policy, and the agent invents neither a cutoff date nor a requirement
based only on schema nullability. It explains why the document cannot be fabricated.

## Review criteria

- Every rule cites supporting application code and handles legitimate exceptions.
- Findings remain simple; new abstractions and services have a demonstrated purpose.
- Detection reports occurrences and repeated runs do not duplicate them.
- Verification rechecks one occurrence without calling detection or changing business data.
- Resolution is assessed explicitly and implemented only when justified.
- Unknown results and invalid input fail clearly without falsely resolving a record.
- Consumer tests cover unhealthy and healthy records, exceptions, changed state,
  repeat execution, and meaningful failure paths.

Do not claim an agent evaluation passed based only on reading the guidance or running
package tests. Preserve observed failures so later changes can be evaluated against them.
