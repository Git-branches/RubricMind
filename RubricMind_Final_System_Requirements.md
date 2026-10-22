# RubricMind: Student Grading and Feedback System

## Final System Requirements

**Application type:** Web-based student essay grading and feedback system  

**Framework:** CodeIgniter 4  

**Backend:** PHP 8.2+  

**Database:** MySQL or MariaDB  

**Development environment:** XAMPP, Composer, and Visual Studio Code

## 1. Main Purpose

RubricMind aims to help teachers evaluate student essays more efficiently, consistently, and transparently by combining structured rubrics, AI-assisted scoring suggestions, evidence-linked assessment, writing-mechanics summaries, and class-level learning insights.

The system supports teachers; it does not replace their professional judgment. AI-generated scores, feedback, and alerts are suggestions only. An authorized teacher must review and finalize every grade before it is released to a student.

## 2. Problems Addressed

1. **Grading fatigue and inconsistency:** Grading many essays can take considerable time, and feedback may vary.

2. **Time-consuming rubric matching:** Teachers must locate essay evidence for each rubric criterion.

3. **Writing-style concerns:** Teachers may notice major changes in a student's writing but need to avoid treating a change as proof of AI use or plagiarism.

4. **Repeated grammar issues:** Repeated mechanics errors can be difficult to summarize across essays.

5. **Limited class-wide insights:** Teachers need a clearer view of common strengths and learning gaps across a class.

## 3. User Roles

### Administrator

- Create, update, deactivate, and manage user accounts.

- Reset user passwords.

- Manage system-level settings.

- Review authorized system activity and audit records.

- Access only the records and actions allowed by the system's permissions.

### Teacher

- Create and manage classes and student enrollments.

- Create student accounts for their own classes. Teachers can only create accounts with the student role.

- Create assignments, instructions, due dates, and structured rubrics.

- View and manage submissions for their classes.

- Run or review AI-assisted essay evaluations.

- Inspect evidence linked to rubric criteria and suggested scores.

- Edit criterion scores and feedback.

- Review writing-style change alerts without treating them as proof of misconduct.

- Finalize grades and control when feedback is released.

- View class performance insights and create/edit draft lesson plans.

### Student
- Register their own student account using the public registration form.
- Sign in and join a teacher's class using a valid class code, or use an account created by a teacher or administrator.
- View their enrolled classes.
- View assigned essay tasks and instructions for their enrolled classes.
- Submit essays through the supported submission method.
- View their own grades and feedback after the teacher releases them.
- Review available feedback and writing-progress information for their own work.
## 4. Functional Requirements

### FR-01: Authentication and Role-Based Access

- Users must sign in to access protected pages.

- The system must enforce permissions on the server, not only by hiding buttons or links.

- Students must not access other students' private submissions or grades.

- Teachers must only manage classes and records they are authorized to access.

- Passwords must be securely hashed.

- Users can reset a forgotten password themselves with "Forgot your password?": the system emails a one-time reset link that expires after 60 minutes. Administrators can still reset any password. Without an internet mail service, emails go to a local test inbox (Mailpit) or the administrator's Email outbox, so the reset also works offline.

### FR-02: Class and Student Management
- Teachers can create, view, update, and manage their classes.
- Each class may have a unique class-joining code that the teacher can view, copy, regenerate, or disable. The system must validate the code before enrolling a student.
- Teachers can enroll or manage students in their classes using either of these supported workflows:
  1. Teacher-created accounts and bulk enrollment (existing workflow): Teachers can add several students at once by entering one "full name, email" per line and selecting a class. A student without an account receives a new student account with a temporary password set by the teacher and must choose a new password at first sign-in. A student who already has an account is enrolled in the selected class without changes to that account.
  2. Student self-registration and class joining (new workflow): Students can register their own accounts using the public registration form. Public registration must create student-role accounts only; users must not be able to register themselves as teachers or administrators. After signing in, a student can select Join a Class, enter a valid class code supplied by a teacher, and join that class. The system must show a clear success or error message.
- Student self-registration must validate required fields and enforce unique email addresses and, where used, unique student IDs. Passwords must be securely hashed. Registration errors must not reveal sensitive account information unnecessarily.
- A student can use one account to join multiple classes. Joining an additional class must create a class-enrollment record, not a duplicate user account.
- The system must prevent duplicate enrollment in the same class. If a student enters a code for a class they already joined, show a message such as "You are already enrolled in this class."
- Teachers can view the students enrolled in their own classes and remove a student from a class they manage. Removing a student from a class must not delete the student's account or erase their submissions, grades, or audit history.
- Emails that belong to teacher or administrator accounts are skipped during teacher bulk enrollment; teachers can never create or change staff accounts.
- Administrators can still create, deactivate, and reset any account.
- The system records class membership and prevents unauthorized access to class records, assignments, submissions, grades, and feedback.
- Class codes must be sufficiently unpredictable to discourage guessing. Teachers must be able to disable or regenerate a code if it is shared improperly. Rate-limit repeated invalid join attempts where practical.
- Existing teacher-created student accounts and class enrollments must continue working after this feature is added. The change must not create duplicate accounts, break existing class records, or require existing users to register again.
### FR-14: Student Self-Registration and Class Joining
- Provide a Create Student Account option on the sign-in page.
- The registration form must collect the required student information defined by the project, such as full name, email, and password; student ID may be required if the institution uses it.
- After successful registration, the student can sign in and use Join a Class to enter a class code.
- Show clear validation states for invalid or disabled codes, duplicate enrollment, and successful enrollment.
- A valid class code enrolls the student in that class only, right away (no teacher approval step; the teacher can remove a student). It must not grant teacher privileges or access to any other class's records.
- Codes of archived classes are refused. Administrators can turn student self-registration off in Settings.
- Do not automatically enroll a newly registered student in any class unless the student completes the class-joining flow or an authorized teacher enrolls them.
- Use the same student account for all classes. Enforce authorization on the server for every class, assignment, submission, grade, and feedback request.
- Preserve the existing teacher bulk-add flow, temporary-password behavior, and first-sign-in password change requirement.
- The feature must work in the local XAMPP defense environment without requiring an external email service. Email verification or activation that depends on email delivery must be optional/configurable and must not be reported as delivered when no mail server is configured.
- Add or update tests for public role restrictions, registration validation, class-code validation, multiple-class enrollment, duplicate enrollment, teacher-created accounts, and unauthorized class access.
### FR-03: Assignment Management

- Teachers can create assignments with a title, instructions, class, due date, and associated rubric.

- Students can see assignments available to their class.

- Teachers can see submissions grouped by assignment and submission status.

- Teachers can close an assignment to stop accepting new submissions.

### FR-04: Structured Rubric Management

- Teachers can create and reuse rubrics.

- Each rubric contains criteria, descriptions, and maximum point values.

- Criteria may include performance levels with descriptions and corresponding scores.

- The system validates scores against rubric limits and calculates totals using the defined scoring rules.

- The rubric version or scoring rules used for a finalized evaluation must be preserved so later rubric edits do not silently change old grades.

- A rubric becomes locked once it has been used in an evaluation. To change a locked rubric, the teacher duplicates it and edits the copy for future assignments.

- In the initial version, each performance level has a fixed point value, a criterion score cannot exceed the criterion's maximum points, and the total score is the sum of all criterion scores.

### FR-05: Essay Submission

- Students can submit essays for assigned tasks.

- Students can type or paste their essay, or upload a PDF or Word (DOCX) file of up to 5 MB. The text is read from the file so the student can check it before submitting, and the original file is kept for the teacher. Administrators can turn uploads off.

- The system records the student, assignment, essay content, submission date, and revision history where applicable.

- The system must validate required fields and handle duplicate or revised submissions clearly.

- Each student has one submission per assignment. The student may edit it until the due date passes or the teacher starts grading it, whichever comes first. After that, the submission is locked.

- Grading starts when the teacher clicks **Start Grading** or requests an AI evaluation for the submission. Viewing or previewing a submission does not lock it.

- Submissions made after the due date are accepted but clearly labeled as **late**, unless the teacher has closed the assignment.

### FR-06: Evaluation and Grade Workflow

Each submission follows a clear workflow:

1. `submitted`

2. `ai_evaluated` (when AI evaluation is enabled)

3. `teacher_reviewed`

4. `finalized` (grading is complete, but not yet visible to the student)

5. `released` (the grade and feedback are visible to the student)

- A submission may move directly from `submitted` to `teacher_reviewed` when the teacher grades manually without AI.

- AI processing is tracked separately with its own status: `pending`, `processing`, `done`, or `failed`. An AI failure must not change or block the main workflow; the teacher can retry the AI request or grade manually.

- The system records status changes and relevant timestamps.

- Manual grading must remain available even if the AI service is unavailable.

- AI must never automatically finalize a grade.

- Only an authorized teacher can finalize an evaluation.

- Changes to a finalized grade must require authorization and be recorded in the audit log.

### FR-07: Consistent Feedback Engine

- Generate structured, balanced, constructive, and encouraging feedback based on the assignment rubric.

- Feedback should identify strengths and actionable areas for improvement.

- Feedback should refer to rubric criteria and essay evidence where available.

- Teachers can edit, accept, or reject AI-generated feedback before release.

### FR-08: Smart Rubric Mapping

- Suggest a score for each rubric criterion.

- Link each suggestion to relevant evidence from the submitted essay when available.

- Evidence quotations must be checked against the original essay before being highlighted.

- If a quotation cannot be reliably located, label it as unverified rather than highlighting unrelated text.

- Teachers can inspect, edit, or reject each score suggestion.

### FR-09: Style Growth Tracker

- Compare a student's current essay with their previous submissions when enough suitable writing history exists.

- Highlight notable changes in writing characteristics for teacher review.

- Sufficient history means at least 2 previous finalized essays by the same student, each containing at least 150 words.

- Display an “insufficient history” state when there are not enough previous submissions.

- Treat style differences as uncertain signals, not proof of AI use, plagiarism, or misconduct.

- Never automatically deduct points, punish a student, or determine misconduct based on a style flag.

### FR-10: Automated Mechanics Summary

- Summarize recurring grammar, punctuation, and writing-mechanics patterns.

- Emphasize repeated patterns instead of overwhelming users with excessive individual corrections.

- Present language-analysis results as suggestions that may require review.

- Allow teachers to use the summary to guide feedback and instruction.

### FR-11: Classroom Insight Dashboard

- Summarize finalized results by class, assignment, and rubric criterion.

- Show common strengths and areas needing improvement.

- Clearly distinguish finalized results from drafts or unreviewed AI suggestions.

- Generate draft lesson-plan ideas based on observed learning gaps.

- Allow teachers to review and edit lesson-plan drafts before using them.

### FR-12: Feedback and Grade Release

- Teachers control when grades and feedback become visible to students.

- Students can view only their own released results.

- Internal teacher notes must be separated from student-facing feedback.

- The system should clearly show whether feedback is pending review, finalized, or released.

### FR-13: Audit Trail

- Record important actions such as score edits, teacher review, grade finalization, feedback release, and changes to finalized grades.

- Preserve the final criterion scores and feedback so the final result can be explained.

- Restrict audit-log access to authorized users

## 5. Non-Functional Requirements

### Security and Privacy
- Validate inputs on the server and safely escape displayed user content.
- Enable CSRF protection for state-changing forms where supported by the framework.
- Keep database credentials and AI API keys out of public files and source control.
- If uploads are implemented, restrict file types and sizes and store files safely.
- Use HTTPS in production.
- Collect only student information needed for the system.
- Inform users as appropriate when student essays are processed by an external AI provider.
- Send only the essay text and rubric to the AI provider. Never send student names, IDs, email addresses, or class details.
- Apply suitable access controls and retention practices for student records.

### Reliability and Data Integrity
- Validate scores and required fields on the server.
- Use database constraints and transactions where appropriate.
- Preserve finalized results and log important changes.
- If an AI request fails or times out, keep the student's essay submission safe and allow manual grading.

### Usability and Accessibility
- Provide a responsive interface for desktop, tablet, and mobile devices.
- Use clear navigation, readable text, helpful validation messages, and visible workflow statuses.
- Do not rely on color alone to communicate evidence or status.
- Support keyboard navigation for core workflows where practical.

### Maintainability
- Follow CodeIgniter 4 conventions.
- Separate controllers, models, views, validation, and AI integration services.
- Keep AI-provider requests in a service layer so the provider can be changed later.
- Use database migrations and seeders where appropriate.
- Document installation, configuration, and testing.

## 6. Proposed Database Entities

The initial database may include:

- `users`
- `classes`
- `class_students`
- `rubrics`
- `rubric_criteria`
- `rubric_levels`
- `assignments`
- `submissions`
- `evaluations`
- `criterion_scores`
- `evidence_highlights`
- `grammar_patterns`
- `style_profiles`
- `style_flags`
- `lesson_plans`
- `audit_logs`

The final schema should use suitable primary keys, foreign keys, validation rules, and relationships. The list is a proposed starting point; fields and relationships should be finalized during implementation.

## 7. Technical Requirements

### Required development tools
- Windows 10/11 or another supported operating system.
- PHP 8.2+ compatible with the installed CodeIgniter 4 version.
- CodeIgniter 4.
- Composer.
- MySQL or MariaDB.
- XAMPP or another compatible local PHP/MySQL environment.
- Visual Studio Code or another code editor.
- A modern web browser.
- Required PHP extensions for the installed framework and database driver, including `intl`, `mbstring`, and the appropriate PDO/MySQL extension as applicable.

### Optional integrations
- An AI provider API for scoring suggestions, feedback, mechanics summaries, style comparisons, and lesson-plan drafts.
- A document-text extraction library if PDF or DOCX uploads are required.
- A charting library for classroom analytics.

The core submission and manual grading workflow must still work when the AI provider is unavailable. API keys must be stored securely in environment configuration, not committed to the repository.

## 8. Acceptance Criteria

The first usable release is acceptable when:

1. Users can sign in and are restricted to pages and records allowed by their roles.
2. Teachers can create classes, assignments, and structured rubrics.
3. Students can submit essays for assigned tasks.
4. Teachers can manually score each rubric criterion without using AI.
5. The system calculates the total score according to the rubric.
6. Teachers can review and edit feedback before releasing it.
7. Submission and evaluation statuses are stored correctly.
8. Students can only see their own grades and feedback after release.
9. Class insights are based on finalized results or are clearly labeled when using provisional data.
10. The Style Growth Tracker shows “insufficient history” when appropriate.
11. AI suggestions never automatically finalize grades or determine misconduct.
12. Important score changes, finalization, and feedback release actions are logged.
13. Essay submissions remain saved if an AI request fails.
14. Evidence quotations that cannot be found in the original essay are labeled "unverified" and are never highlighted.

## 9. Recommended Development Phases

1. **Foundation:** Configure CodeIgniter 4, database, migrations, authentication, and role permissions.
2. **Core management:** Build classes, student enrollment, assignments, rubrics, and essay submissions.
3. **Manual grading:** Build rubric-based scoring, feedback editing, grade finalization, and release controls.
4. **AI assistance:** Add feedback suggestions, rubric mapping, evidence verification, and mechanics summaries.
5. **Insights:** Add style comparisons, classroom analytics, and editable lesson-plan drafts.
6. **Testing and deployment:** Test permissions, scoring calculations, validation, AI failure handling, privacy, and deployment configuration.

## 10. Important Scope Decisions

- **Teacher authority:** The teacher remains responsible for the final grade.
- **AI reliability:** AI outputs are suggestions and may be incorrect; they must be reviewable and editable.
- **Academic integrity:** Writing-style changes must not be treated as proof of AI use or plagiarism.
- **Submission method:** Typed or pasted essay text, or an uploaded PDF/DOCX file whose text the student checks before submitting.
- **Implementation order:** Finish authentication, database structure, submissions, and manual grading before relying on AI features.
- **Current project status:** All planned features are built and run locally on CodeIgniter 4 with MariaDB: sign-in with administrator, teacher, and student roles and an audit log; student self-registration and class codes; classes, enrollment, rubrics, assignments, and essay submissions (typed or PDF/DOCX); manual grading, finalizing, releasing, and reopening grades; AI suggestions (a built-in rule-based Demo AI or Google Gemini) with evidence highlights and mechanics summaries; the Style Growth Tracker; the Classroom Insight Dashboard with lesson-plan drafts; self-service password reset; and system settings with data retention. The automated test suite passes. Remaining before real use with students: production deployment settings (HTTPS, secure cookies, production mode), removing the demo accounts, turning off Defense Demo Mode, and replacing the Gemini API key.

## 11. Out of Scope (Initial Version)

The following are not included in the initial version of RubricMind:

- **Handwritten or scanned essays:** The system does not read photos or scans of handwritten work (no OCR). Scanned PDFs are refused with a message.
- **Other file types:** Only PDF and DOCX uploads are supported (not .doc, images, or other formats).
- **LMS integration:** No connection to Google Classroom, Moodle, Canvas, or other learning management systems.
- **Internet plagiarism checking:** The system does not compare essays against websites, publications, or external plagiarism databases. The Style Growth Tracker compares a student only with their own previous work.
- **AI-detection verdicts:** The system does not declare whether an essay was written by AI.
- **Co-teachers and shared classes:** Each class belongs to one teacher.
- **Parent or guardian accounts:** Only administrators, teachers, and students have accounts.
- **Fully automatic grading:** Every grade requires teacher review and finalization.
- **Native mobile apps:** RubricMind is a responsive web application only.
