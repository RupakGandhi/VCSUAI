# VCSU developer handoff: application repairs and acceptance checks

Prepared September 13, 2026, from the September 12–13 audit and published content verification. These are findings from that audited version; reproduce them against the current live source before changing it.

**Goal:** make the platform consistently render the current CMS curriculum, execute its advertised workflows, and support future content updates without another manual repair of hard-coded module/role mappings.

**Application:** [VCSU AI Institute](https://aqua-marten-245046.hostingersite.com/site/P9UAO6eozleG5yIvO0XdGDwL). Access credentials are intentionally excluded from this package. Use the established access process.

## Start here

1. Back up the current application and VCSU data. Identify the actual production template, API routes and client-scoping mechanism. The CMS Export page references `storage/app/static-template/index.html`; confirm how it relates to the live page in your implementation.
2. Preserve the published curriculum. The attached `vcsu-content-repair-2026-09-12.json` is a reference snapshot already published, not an instruction to import it again. Its SHA-256 is `95dcc4b9702505c47e41a316e91e5c1c0843ed34a8a887256074570e58fb6cff`.
3. Use the defect descriptions and acceptance checks below as requirements. The sample code is an implementation reference derived from an exported page, not a production-ready replacement for your source. Adapt or replace it using your architecture.
4. Preserve the existing design, CMS editing/import/export workflow, module IDs, current ILOs, client separation and access controls. If a change must affect other institutes, identify that scope and test those clients. Avoid VCSU-specific assumptions in shared code.

Current content: 4 strands, 10 courses, 31 modules, 6 roles, 558 Practice prompts, 558 Apply choices, 558 primary simulations, 1,674 follow-ups, and 372 Mastery Prompts. Custom Deliverable is an additional shared UI workflow. Counts describe the audited content, not values to hard-code into the application.

## Evidence labels

- **Live:** directly observed through the learner/admin interface.
- **Source:** found in the downloaded/exported application code. Map the finding to the actual production implementation before choosing the repair.
- **Unverified:** an acceptance check remains open; this is not proof that the feature is broken.

## D01 — Missing T4 course navigation

**Evidence: Live + Source.** On the Teaching home, course cards showed T1, T2 and T3. T4 and its three modules existed in CMS content but lacked a normal course entry. Runtime cloning of module markup did not create a complete course experience.

**Required behavior:** T4 and T4.1–T4.3 must be reachable through normal navigation, with their own current titles/content. Course lists, previous/next navigation, breadcrumbs and course review must use the current curriculum. Adding/updating CMS content must not leave those surfaces behind.

**Acceptance:** enter through Teaching → T4 → each module; traverse previous/next and return to the course/home. Repeat for all current courses in a fresh session and after reload. No orphan module, cloned T1.1 content, nonexistent module or inaccessible course. Test a curriculum update in staging to verify synchronization.

**Source hints only:** exported `COURSE_ORDER`, `COURSE_MODULES`, `MODULE_ORDER`, and course/module reconciliation. Use your equivalent data-driven design; do not assume these globals are the right production abstraction.

## D02 — Role switching and stale state

**Evidence: Live + Source.** Six T1.1 role-switch checks produced `ReferenceError: updateMattersForRole is not defined`. A previous role's conversation could remain visible. Role-specific Practice text changed, so this is not a claim that every role surface failed to render.

**Required behavior:** changing role must update Overview, Learn, Practice, Apply, Mastery, facilitator content and relevant export context without exceptions. Role-specific conversations and pending uploads must be reset or explicitly isolated so they cannot be mistaken for the newly selected role's work.

**Acceptance:** test all six roles across all 31 modules; include switching after a simulation and after choosing a file, then opening another module. Verify the selected role's content and absence of stale-role output, with no related console errors.

**Source hints only:** helper scope around `updateMattersForRole`; replacement order for Learn HTML and role strategies; role-change state handling.

## D03 — Prompt copy, multiline entry and answer matching

**Evidence: Live + Source.** Pasting a multiline prompt into the single-line simulator field joined words at line boundaries, such as `evidence.TRAINING INPUTND`. Source copy handlers reported success without waiting for the clipboard result. These are separate problems; clipboard behavior was not certified for every browser.

**Required behavior:** copy the complete displayed prompt and preserve meaningful spacing/line breaks. Confirm copy only after success and provide a selectable manual-copy fallback if unavailable. Use an input appropriate for multiline prompts with visible keyboard instructions. Route a prepared prompt to its own module/role/option response and each follow-up to its own continuation. Unrecognized text must not silently receive an unrelated canned response.

**Acceptance:** round-trip the displayed prompt through Copy/Paste/Send; test multiline/CRLF text, clipboard denial and keyboard entry. Exercise all 558 prepared prompts and all 1,674 follow-up mappings through appropriate automated integration checks. Include unrelated text and changing roles mid-conversation. Human-check rendered examples from each workflow type.

**Implementation discretion:** explicit prompt IDs, controlled case selection, or carefully normalized matching are possible approaches. Exact text matching in the supplied reference is one option, not a requirement to make punctuation or harmless whitespace unusably strict. The audit found that prompt and simulation sort orders do not consistently share the same indexing scheme; do not pair records solely on equal sort-order numbers.

## D04 — Practice instructions must describe actual behavior

**Evidence: Live.** Hard-coded instructions still say a scenario may intentionally mismatch the user's role, invite bracket substitutions, and invite arbitrary variations despite the prepared-response design.

**Required behavior:** explain the actual supported Practice workflow. Prepared cases should be described as prepared role-specific examples. Contextual customization belongs in the supported Apply/external-tool workflow unless the application actually supports generative custom input.

**Acceptance:** follow only the visible instructions as a first-time participant. Every advertised action must either work or clearly explain its supported limits before an incorrect result is presented. Keep the revised CMS prompts intact.

## D05 — File upload behavior and sample integrity

**Evidence: Source; sample links partly verified live.** The audited upload logic did not establish that the selected file's contents were analyzed and could fall back to a prepared response. Fourteen current Practice entries reference samples across ten modules: D1.1, L1.2, L2.1, T1.1, T1.2, T1.3, T2.2, T2.3, T3.1 and T3.2. All ten module upload flags are now enabled in CMS.

**Required behavior:** implement and label the product's agreed upload behavior. If a case uses a prepared sample walkthrough, verify the actual corresponding file and identify the output as prepared. If participants are promised analysis of their own files, actual parsing/analysis and supported-format/error handling must be implemented. A filename alone is never evidence of analysis.

**Acceptance:** test all 14 sample-linked entries: download, open, upload, select the correct prompt and inspect the result. Try the wrong sample, a same-named file with changed content, empty/unsupported files, missing files and a file selected before a role change. None may receive a misleading analysis of unexamined content. In a generative implementation, changed supported input must be reflected in the output; in a prepared implementation it must be clearly rejected or redirected.

**Scope note:** the supplied patch reads/verifies TXT/CSV sample bytes and returns a labeled prepared walkthrough. It does **not** implement arbitrary-file generative analysis. Do not use that patch to silently reduce promised functionality. OptimizED must resolve any difference between the contracted workflow and current capabilities.

Three broken references were replaced in CMS. D1.1 and T1.1 replacements were checked. L1.2 was saved and observed inline, but a later exact recheck was blocked by the audit browser's URL policy; verify it in your environment rather than treating the browser restriction as a server failure.

## D06 — Survey persistence and truthful completion

**Evidence: Source + limited Live.** Exported code contained a loopback API base and completion logic that did not require a successful submission receipt. The VCSU admin survey table showed zero records. This was not a live network trace proving the production endpoint; inspect the real implementation.

**Required behavior:** submit to the actual configured service, use its real authentication/client scope and validate its success contract. Do not display “saved/submitted” or award survey-dependent completion before required data is accepted. Retain answers and offer recovery when submission fails. Prevent accidental duplicate submissions. Use the intended completion policy; an optional survey should not become a new mandatory requirement without confirming the product rule.

**Acceptance:** submit a clearly labeled QA response in an authorized test environment and verify the exact module/role/client record in the admin view. Test rejected/server-error/offline responses and retry. Verify no premature success, lost answers or duplicate record. Verify client isolation, including another institute if the template is shared. Do not delete production data as part of testing; use staging or clearly identified QA handling.

**Source hints only:** `VCSU_API_BASE` and `submitSurvey`. Same-origin `/api/v1` in the reference is provisional. Confirm the actual routes and receipt schema; do not copy that URL assumption blindly.

## D07 — Search, pathways and current curriculum mappings

**Evidence: Source; stale accessible statistics also Live.** Several derived surfaces still used old curriculum lists/titles. The home visibly showed updated numeric totals while some accessible labels retained old totals.

**Required behavior:** search, strand filters, pathway module lists, breadcrumbs, course counts and accessible labels must reflect current content. Pathway durations must match their displayed agendas; production/pilot time should be described accurately.

**Acceptance:** find F1.1–F1.3, T2.4 and T4.1–T4.3 by current title/topic; verify obsolete T3.4 is not offered. Check every displayed pathway and its links. Confirm visible and assistive labels agree. Recheck after a staging CMS content change.

## D08 — Apply instructions and Custom Deliverable

**Evidence: Source.** Revised artifact-specific descriptions coexist with hard-coded generic steps. Description content is rendered as escaped plain text; HTML inserted there would be displayed literally. Custom Deliverable exists and is not a missing-feature finding.

**Required behavior:** display the current numbered instructions readably, without contradicting the specific artifact's production/save/verification steps. Initial and refinement prompts must remain correctly paired. Custom Deliverable must use the selected module's current ILOs and relevant role context, including T4.

**Acceptance:** test each module/role's Apply controls and text bindings; inspect representative document, spreadsheet, video, translation and workload tasks. Initial/refinement Copy controls must copy only the intended text. Check Custom Deliverable in existing and added modules. A draft script must not be labeled a completed video or exported artifact.

## D09 — Certificates and completion rules

**Evidence: Source.** Certificate/review functions reference outdated dictionaries and mappings; current titles, ILOs and role context need reconciliation. Full certificate completion/export behavior was not certified live.

**Required behavior:** issue certificates under the intended module/course completion rules, using current learner/module/course/role information as appropriate. Course requirements must come from the current curriculum. Avoid duplicate certificate containers and stale inherited text.

**Acceptance:** verify incomplete and completed states, course prerequisites, revisit/reload behavior, current T4 information, and downloaded/printed output. A certificate must not claim completion when required steps are still incomplete. Document the intended rules if they are not already specified.

## D10 — Facilitator content and exports

**Evidence: Source + Unverified access.** Role-specific facilitator content was published, but exported code used older guide/title dictionaries. The audit's secure facilitator sign-in did not complete; this is not proof that the password or authentication feature is defective.

**Required behavior:** after the existing authorized unlock, show and export the current module/role's CMS facilitator content, including Close the Loop. Maintain access controls and match the export to the visible guide.

**Acceptance:** successfully unlock using the established process, switch roles/modules, verify current guides, and open each guide export. Include T4 and compare against the displayed content; check formatting/page breaks. Test a locked session to confirm access remains restricted. Report any reproducible authentication issue separately with its actual error.

## D11 — Standalone export and file packaging

**Evidence: Downloaded package.** The audited archive contained obsolete/mislabelled legacy artifacts. Six normal `.docx` files were not valid Office ZIP packages: `T2.2-differentiation-matrix.docx`, `D1.1-needs-assessment.docx`, `L1.2-learning-pathway.docx`, `T3.4-assessment-redesign.docx`, `T3.2-student-feedback.docx`, and `T3.1-assessment-rubric.docx`. A Word lock file was also present. Review actual references before removing/replacing files.

**Required behavior:** export the current curriculum and all referenced required assets, with correct file formats and working relative links. Exclude obsolete/unreferenced temporary artifacts. Keep online behavior and standalone behavior deliberately separate. The CMS currently describes static exports as disabling surveys/tracking; do not deploy that static mode as the live institute.

**Acceptance:** extract the new ZIP to a clean folder, serve it in its intended standalone environment, traverse courses/modules/roles, and open every linked sample/artifact. Where true offline operation is promised, test without the network. Verify current content and no unnecessary dependency on the original CMS. Document features that intentionally require an online service.

## D12 — Targeted accessibility and browser regression

**Evidence: Source + observed stale labels.** The reference repair includes phase tab/panel associations, keyboard navigation, upload labels and expanded-state announcements. This is not a complete accessibility audit or WCAG certification.

**Required behavior:** the repaired flows must remain usable with keyboard navigation, visible focus and accurate accessible labels, including multiline input and file selection.

**Acceptance:** keyboard-only navigation through role choice, course/module entry, phases, copy/send, upload and facilitator controls; check layout at a normal desktop size and a narrow/mobile width. State the browsers tested. Do not equate these checks with complete WCAG conformance.

## Responsibilities and unresolved product decisions

**Developer:** reproduce findings against current code; repair application behavior; preserve client/CMS behavior; supply deployment/version details and evidence for the acceptance checks.

**OptimizED:** confirm the latest proposal/email commitments, approve any real change in product scope, finish curriculum/artifact execution checks and trainer acceptance. The historical discussion mentioned four follow-up choices, while current data has three. Confirm the requirement before adding a fourth or accepting three. Likewise, confirm final role and upload promises; do not infer them from old audit summaries.

The 558 external Apply workflows have not all been executed. One D1.2 Teacher prompt/refinement was tried in ChatGPT and a corresponding workbook was built/tested separately; sampled T1.1 simulations were exercised live. Platform regression tests do not replace remaining external-tool/content trials.

## How to use the supplied code

If earlier patch notes differ from this handoff, follow this handoff’s requirements and confirm the intended product behavior before implementation.

`VCSU-Runtime-Source-Patch-2026-09-12.zip` contains reference helpers, an exact-match builder, focused tests, a manifest and earlier repair notes. The helpers address the audited exported code. Use the behavior and tests as guidance; port the logic to your real implementation.

The builder expects the audited baseline at `vcsu-backend-static-baseline/index.html`; parts of the test script expect generated review files. Those full baseline/generated HTML files are intentionally not in this handoff. **This is not a self-contained installer or a claim that its tests will run unchanged against your repository.** The manifest records the baseline/candidate hashes. Twenty-six checks passed in the audit workspace, not against your deployed app. Adapt the tests to your source and rerun them.

Do not put application scripts into editable course content to work around the template boundary. Do not upload an exported offline page over the online application. Keep authoritative content in CMS and derive or reliably synchronize the application surfaces from it.

## Requested return evidence

Return a list keyed to D01–D12 with: status (fixed / already working with evidence / blocked / needs scope decision), affected files/components, deployment version/time, tested browser, test result and any remaining limitation. Provide a staging or updated live link. A concise recording is useful for T4 navigation, role switching, Copy/Paste/Send, file handling, survey-to-admin persistence, and facilitator/certificate exports.

For each ticket claimed fixed, show its acceptance check passing against the deployed implementation. If the live implementation differs from the audited export, explain the difference rather than forcing the reference patch onto it.

## Sources

OpenAI. (2026a, September 13). *VCSU published repairs and handoff status* [AI-assisted internal technical audit]. Included as `VCSU-Published-Repairs-and-Handoff-Status-2026-09-12.md`.

OpenAI. (2026b, September 13). *VCSU application source repair* [Reference patch and local verification notes]. Included inside `VCSU-Runtime-Source-Patch-2026-09-12.zip`.

VCSU AI Institute content-management system. (2026, September 12). *VCSU content export, 21:55:30* [JSON data set]. Exact comparison and rendering records are included in `VCSU-Repair-Verification-Evidence-2026-09-12.zip`.

These sources document the observed build and local checks; they do not establish the current production architecture or contractual terms.
