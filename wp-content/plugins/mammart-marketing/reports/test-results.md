# T2-PH — kết quả thực thi

SIMULATION ONLY. PHP 8.0.30. Executed UTC: 2026-11-12T17:27:24+00:00

PASS: 29; FAIL: 0; BLOCKED: 1.

| ID | Test | Result | Evidence / limitation |
|---|---|---|---|
| T01 | Consent defaults false; profile flags do not opt in | PASS | Assertions executed successfully. |
| T02 | Before six-month milestone | PASS | Assertions executed successfully. |
| T03 | Issue on milestone and expire at next month boundary | PASS | Assertions executed successfully. |
| T04 | Late signup waits until next calendar day under explicit trial policy | PASS | Assertions executed successfully. |
| T05 | Last-day signup receives no next-month catch-up | PASS | Assertions executed successfully. |
| T06 | Repeated issuance is idempotent | PASS | Assertions executed successfully. |
| T07 | Two PHP processes contend for the same issuance | PASS | Assertions executed successfully. |
| T08 | Two babies, one parent, separate benefits | PASS | Assertions executed successfully. |
| T09 | Missing, impossible, future and unapproved DOB | PASS | Assertions executed successfully. |
| T10 | Approved DOB replaces old schedule before issuance | PASS | Assertions executed successfully. |
| T11 | DOB changed after issuance does not create or rewrite benefit | PASS | Assertions executed successfully. |
| T12 | Duplicate, stale and wrong-owner DOB events | PASS | Assertions executed successfully. |
| T13 | Unresolved 29/30/31-day boundaries including leap-year February | PASS | Assertions executed successfully. |
| T14 | Withdraw before issuance stops issuance and sending | PASS | Assertions executed successfully. |
| T15 | Withdraw after issuance retains old usable coupon | PASS | Assertions executed successfully. |
| T16 | Failed email retries the same persisted code | PASS | Assertions executed successfully. |
| T17 | Consent checked again immediately before retry | PASS | Assertions executed successfully. |
| T18 | Anonymous or another customer cannot redeem | PASS | Assertions executed successfully. |
| T19 | Expiry inclusive end of month and exclusive next-month midnight | PASS | Assertions executed successfully. |
| T20 | Eligible-only basket discounts fifteen percent | PASS | Assertions executed successfully. |
| T21 | Mixed basket excludes food and other items; shipping unchanged | PASS | Assertions executed successfully. |
| T22 | Food-only and unrelated-only baskets cannot get discount | PASS | Assertions executed successfully. |
| T23 | Variation eligibility must be explicit; no parent inheritance | PASS | Assertions executed successfully. |
| T24 | One coupon per cart and per order, both application orders | PASS | Assertions executed successfully. |
| T25 | Successful fake use prevents subsequent use | PASS | Assertions executed successfully. |
| T26 | Duplicate success event cannot consume twice | PASS | Assertions executed successfully. |
| T27 | Pending, paid, cancelled, partial and full refunds have no automatic usage mapping | PASS | Assertions executed successfully. |
| T28 | Unconfirmed pricing blocks; explicitly labelled minimum, cap and rounding trials | PASS | Assertions executed successfully. |
| T29 | Restart after durable issue-before-send; transaction rollback and invalid inputs | PASS | Assertions executed successfully. |
| T30 | Live WordPress role and nonce protection | BLOCKED | CLI runner does not execute live WordPress sessions. Manual T30 evidence is tracked separately; do not mark live nonce/role checks PASS from CLI or stub tests. |

PENDING_POLICY cases test safe deferral only. T30 requires a live WordPress environment. No actual checkout or email test has passed.
