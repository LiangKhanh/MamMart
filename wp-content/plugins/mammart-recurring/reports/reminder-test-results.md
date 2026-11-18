# Reminder tests

SIMULATION DATA ONLY

15 PASS, 0 FAIL, 0 DEFERRED

## REM-01 — PASS

Active plan sends one T-2 reminder

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-14T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":1,"count":1,"events":["REMINDER_CREATED","EMAIL_SENT"],"keys":["PLAN-REM-001@2026-12-16"],"reminder_date":"2026-12-14","reminder_status":"SENT","sent_at":"2026-12-14T08:00:00+00:00"}`
- Actual: `{"attempts":1,"count":1,"events":["REMINDER_CREATED","EMAIL_SENT"],"keys":["PLAN-REM-001@2026-12-16"],"reminder_date":"2026-12-14","reminder_status":"SENT","sent_at":"2026-12-14T08:00:00+00:00"}`

## REM-02 — PASS

Three runs during T-2 send once

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-14T08:00:00+00:00","2026-12-14T08:05:00+00:00","2026-12-14T12:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":1,"count":1,"events":["REMINDER_CREATED","EMAIL_SENT","DUPLICATE_SKIPPED","DUPLICATE_SKIPPED"],"keys":["PLAN-REM-001@2026-12-16"],"reminder_date":"2026-12-14","reminder_status":"SENT","sent_at":"2026-12-14T08:00:00+00:00"}`
- Actual: `{"attempts":1,"count":1,"events":["REMINDER_CREATED","EMAIL_SENT","DUPLICATE_SKIPPED","DUPLICATE_SKIPPED"],"keys":["PLAN-REM-001@2026-12-16"],"reminder_date":"2026-12-14","reminder_status":"SENT","sent_at":"2026-12-14T08:00:00+00:00"}`

## REM-03 — PASS

Before T-2 there is no reminder

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-13T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":0,"count":0,"events":[]}`
- Actual: `{"attempts":0,"count":0,"events":[]}`

## REM-04 — PASS

Paused plan is skipped

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Paused","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-14T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":0,"count":0,"events":[],"plan_status":"Paused"}`
- Actual: `{"attempts":0,"count":0,"events":[],"plan_status":"Paused"}`

## REM-05 — PASS

Cancelled plan is skipped

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Cancelled","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-14T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":0,"count":0,"events":[],"plan_status":"Cancelled"}`
- Actual: `{"attempts":0,"count":0,"events":[],"plan_status":"Cancelled"}`

## REM-06 — PASS

Pending plan is skipped

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"PendingActivation","frequency_days":28,"completed_at":null,"expected_date":null,"runs":["2026-12-14T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":0,"count":0,"events":[],"plan_status":"PendingActivation"}`
- Actual: `{"attempts":0,"count":0,"events":[],"plan_status":"PendingActivation"}`

## REM-07 — PASS

Fake mail failure is logged

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-14T08:00:00+00:00"],"fake_mail_failure_reason":"SIMULATED_MAIL_FAILURE","confirmed_cycles":[]}`
- Expected: `{"attempts":1,"count":1,"events":["REMINDER_CREATED","EMAIL_FAILED"],"failure_reason":"SIMULATED_MAIL_FAILURE","reminder_status":"FAILED","sent_at":null}`
- Actual: `{"attempts":1,"count":1,"events":["REMINDER_CREATED","EMAIL_FAILED"],"failure_reason":"SIMULATED_MAIL_FAILURE","reminder_status":"FAILED","sent_at":null}`

## REM-08 — PASS

Failed mail is neither retried nor duplicated

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-14T08:00:00+00:00","2026-12-14T08:05:00+00:00"],"fake_mail_failure_reason":"SIMULATED_MAIL_FAILURE","confirmed_cycles":[]}`
- Expected: `{"attempts":1,"count":1,"events":["REMINDER_CREATED","EMAIL_FAILED","DUPLICATE_SKIPPED"],"failure_reason":"SIMULATED_MAIL_FAILURE","reminder_status":"FAILED","sent_at":null}`
- Actual: `{"attempts":1,"count":1,"events":["REMINDER_CREATED","EMAIL_FAILED","DUPLICATE_SKIPPED"],"failure_reason":"SIMULATED_MAIL_FAILURE","reminder_status":"FAILED","sent_at":null}`

## REM-09 — PASS

Expected date has not ended at 23:59:59 UTC

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-16T23:59:59+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":0,"count":0,"events":[],"expected_date":"2026-12-16","plan_status":"Active"}`
- Actual: `{"attempts":0,"count":0,"events":[],"expected_date":"2026-12-16","plan_status":"Active"}`

## REM-10 — PASS

The day after expected date pauses the plan without advancing the cycle

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-17T00:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":0,"count":0,"events":["PLAN_PAUSED_MISSED_DATE"],"expected_date":"2026-12-16","pause_reasons":["MISSED_EXPECTED_DATE"],"plan_status":"Paused"}`
- Actual: `{"attempts":0,"count":0,"events":["PLAN_PAUSED_MISSED_DATE"],"expected_date":"2026-12-16","pause_reasons":["MISSED_EXPECTED_DATE"],"plan_status":"Paused"}`

## REM-11 — PASS

Repeating an expired run logs only one pause

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-17T00:00:00+00:00","2026-12-18T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":0,"count":0,"events":["PLAN_PAUSED_MISSED_DATE"],"expected_date":"2026-12-16","plan_status":"Paused"}`
- Actual: `{"attempts":0,"count":0,"events":["PLAN_PAUSED_MISSED_DATE"],"expected_date":"2026-12-16","plan_status":"Paused"}`

## REM-12 — PASS

Two plans on the same date have distinct keys

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-14T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[],"second_plan_id":"PLAN-REM-002"}`
- Expected: `{"attempts":2,"count":2,"keys":["PLAN-REM-001@2026-12-16","PLAN-REM-002@2026-12-16"]}`
- Actual: `{"attempts":2,"count":2,"keys":["PLAN-REM-001@2026-12-16","PLAN-REM-002@2026-12-16"]}`

## REM-13 — PASS

Two cycles for one plan have distinct keys

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-14T08:00:00+00:00","2027-01-11T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[],"cycle_2_completed_at":"2026-12-16","cycle_2_expected_date":"2027-01-13"}`
- Expected: `{"attempts":2,"count":2,"keys":["PLAN-REM-001@2026-12-16","PLAN-REM-001@2027-01-13"]}`
- Actual: `{"attempts":2,"count":2,"keys":["PLAN-REM-001@2026-12-16","PLAN-REM-001@2027-01-13"]}`

## REM-14 — PASS

A confirmed cycle does not pause after its expected date

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-17T00:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":["PLAN-REM-001@2026-12-16"]}`
- Expected: `{"attempts":0,"count":0,"events":[],"expected_date":"2026-12-16","plan_status":"Active"}`
- Actual: `{"attempts":0,"count":0,"events":[],"expected_date":"2026-12-16","plan_status":"Active"}`

## REM-15 — PASS

Missing the T-2 run does not send a late reminder at T-1

- Input: `{"plan_id":"PLAN-REM-001","initial_status":"Active","frequency_days":28,"completed_at":"2026-11-18","expected_date":"2026-12-16","runs":["2026-12-15T08:00:00+00:00"],"fake_mail_failure_reason":null,"confirmed_cycles":[]}`
- Expected: `{"attempts":0,"count":0,"events":[],"plan_status":"Active"}`
- Actual: `{"attempts":0,"count":0,"events":[],"plan_status":"Active"}`
