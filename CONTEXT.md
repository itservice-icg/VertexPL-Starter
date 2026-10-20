# NCR Workflow Context

This glossary defines the NCR lifecycle terms used by the application.

## Lifecycle

**Draft (`DRAFT`)**: An NCR report being prepared by its reporter. Submitting it moves directly into Initial Review; resubmitting after correction returns it to Initial Review as well.

**Initial Review (`INITIAL_REVIEW`)**: The first review stage for a submitted NCR. A reviewer records whether the information is complete and, when complete, proposes a disposition.

**Returned for Correction (`RETURNED_FOR_CORRECTION`)**: An NCR sent back to its reporter for changes before it re-enters Initial Review.

**Submitted (`SUBMITTED`)**: _Avoid_: a separate lifecycle state between Draft and Initial Review.

**Pending Manager Approval (`PENDING_MANAGER_APPROVAL`)**: A reviewed NCR whose proposed disposition is waiting for a Plant Manager decision.

**Returned for Disposition Revision (`RETURNED_FOR_DISPOSITION_REVISION`)**: An NCR whose proposed disposition was rejected and must be revised before resubmission.

**Disposition Approved (`DISPOSITION_APPROVED`)**: The disposition is approved and the responsible department is preparing, revising, or awaiting approval of the current Corrective Action plan. The NCR status alone does not identify the plan's approval state; the current Action Cycle and Action statuses do.

**Pending Destruction Approval (`PENDING_DESTRUCTION_APPROVAL`)**: A `SCRAP` disposition whose Corrective Action plan is approved but whose destruction decision is still pending.

**Action in Progress (`ACTION_IN_PROGRESS`)**: The current Corrective Action plan may be executed by the responsible department.

**Pending Action Verification (`PENDING_ACTION_VERIFICATION`)**: The responsible department has submitted the current-cycle Actions for independent verification.

**Returned for Rework (`RETURNED_FOR_REWORK`)**: Action Verification or QA Follow-up determined that more work is required. Starting Rework creates a new Corrective Action Cycle.

**QA Verified (`QA_VERIFIED`)**: Corrective Actions passed verification and QA is evaluating their effectiveness.

**Closed (`CLOSED`)**: QA recorded an `EFFECTIVE` follow-up and the NCR lifecycle is complete.

## Corrective Action

**Corrective Action Cycle**: One approval and execution round for a set of Corrective Actions. Rework opens a new Cycle and retains the prior Cycle as history.

**Corrective Action Plan**: The plan recorded by the responsible department and frozen as a snapshot when submitted for approval.

**Responsible Department**: The department selected by the approved disposition. Its users need `ncr.action.manage` to prepare and execute Corrective Actions.

**Action Verification**: The decision that confirms whether completed Corrective Actions were performed satisfactorily. It is distinct from QA Follow-up.

**QA Follow-up**: The later evaluation of whether verified Corrective Actions are effective. `EFFECTIVE` closes the NCR; `NOT_EFFECTIVE` returns it for Rework; `NEED_MORE_TIME` keeps it in QA Follow-up.
