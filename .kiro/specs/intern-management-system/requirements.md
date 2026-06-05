# Requirements Document

## Introduction

The Intern Management System (IMS) is a web-based platform for TDT Powersteel Corp. that enables Admin and HR Staff to manage intern records across departments. The system provides a structured, department-based workflow covering secure authentication, intern profile management (201 Profile), daily time record (DTR) tracking, and requirement document monitoring. It emphasizes data preservation through archiving over deletion, maintains an audit trail for accountability, and supports export and print capabilities for reporting.

---

## Glossary

- **IMS**: Intern Management System — the platform described in this document.
- **Admin**: A user role with full access to all system features, including user management and configuration.
- **HR_Staff**: A user role with access to intern records, DTR, and requirement monitoring within assigned departments.
- **User**: Any authenticated person using the IMS (Admin or HR_Staff).
- **Department**: An organizational unit within TDT Powersteel Corp. that contains and manages a group of interns.
- **Intern**: A person undergoing an internship program assigned to a specific Department.
- **Dashboard**: The main landing page displayed after login, showing all Departments and their active intern counts.
- **Department_View**: The page displaying the full list of Interns assigned to a selected Department.
- **Intern_Workspace**: The dedicated page for a single Intern, containing the 201 Profile, DTR Module, and Requirement Monitoring Module.
- **201_Profile**: The section of the Intern_Workspace containing personal, academic, and internship-related information for an Intern.
- **DTR_Module**: The Daily Time Record section of the Intern_Workspace, used to record and compute attendance data.
- **DTR_Entry**: A single row in the DTR_Module representing one day's attendance record (date, time-in, time-out, computed hours).
- **Requirement_Module**: The section of the Intern_Workspace used to track required documents and their submission statuses.
- **Requirement_Item**: A single document requirement listed in the Requirement_Module with a status, submission date, and remarks.
- **Rendered_Hours**: The total number of hours an Intern has completed, computed from DTR_Entry records.
- **Remaining_Hours**: The difference between the Intern's required internship hours and Rendered_Hours.
- **Overtime**: Hours worked beyond 8 hours in a single DTR_Entry day.
- **Undertime**: Hours short of 8 hours in a single DTR_Entry day.
- **Archive**: A soft-delete action that marks a record as inactive and removes it from the default active view without permanently deleting it.
- **Audit_Trail**: A background log that records all significant create, update, archive, login, and logout actions performed by Users.
- **Requirement_Status**: The state of a Requirement_Item — one of: Pending, Submitted, or Approved.
- **Session**: An authenticated period of activity for a User within the IMS.

---

## Requirements

### Requirement 1: User Authentication

**User Story:** As an Admin or HR Staff member, I want to log in securely, so that only authorized users can access intern data.

#### Acceptance Criteria

1. WHEN a User submits a username and password matching a registered account, THE IMS SHALL authenticate the User and redirect them to the Dashboard.
2. WHEN a User submits invalid credentials, THE IMS SHALL display an error message on the current login page without redirecting and deny access.
3. WHEN a User's Session is inactive for 30 consecutive minutes, THE IMS SHALL automatically terminate the Session and redirect the User to the login page; IF the redirect fails, THE IMS SHALL display a session-expired message on the current page without requiring additional user action.
4. WHEN a User clicks the logout control, THE IMS SHALL terminate the Session and redirect the User to the login page.
5. WHILE a User is not authenticated, THE IMS SHALL redirect any attempt to access a protected page to the login page.
6. WHEN a User submits invalid credentials 5 consecutive times for the same account, THE IMS SHALL lock that account and display a lockout message, preventing further login attempts until an Admin unlocks the account.

---

### Requirement 2: Dashboard — Department Overview

**User Story:** As a User, I want to see all departments and their active intern counts on the dashboard, so that I can quickly assess intern distribution across the organization.

#### Acceptance Criteria

1. WHEN a User successfully authenticates, THE IMS SHALL display the Dashboard containing all Departments.
2. THE IMS SHALL display each Department with its name and the count of Interns with status Active assigned to it, including Departments with zero active Interns, ordered alphabetically by Department name.
3. WHEN a User selects a Department on the Dashboard, THE IMS SHALL navigate the User to the Department_View for that Department.
4. IF the Dashboard fails to load Department data due to a system error, THE IMS SHALL display an error message and a retry option without navigating away from the Dashboard.
5. WHEN the IMS contains no Departments, THE IMS SHALL display an empty-state message on the Dashboard indicating that no departments are configured.

---

### Requirement 3: Department View — Intern List Management

**User Story:** As a User, I want to view, search, filter, and manage interns within a department, so that I can efficiently access and maintain intern records.

#### Acceptance Criteria

1. WHEN a User opens a Department_View, THE IMS SHALL display the full list of active Interns assigned to that Department.
2. WHEN a User enters a search term of 1 to 100 characters in the Department_View, THE IMS SHALL filter the displayed Intern list to show only Interns whose names contain the search term as a case-insensitive substring match.
3. WHEN a User applies a status filter in the Department_View, THE IMS SHALL filter the displayed Intern list to show only Interns whose status matches the selected value (Active or Archived).
4. THE IMS SHALL display each Intern entry in the Department_View with the Intern's name, status, and Rendered_Hours, applying the same display format to all views including filtered and search results.
5. WHEN a User selects an Intern entry in the Department_View, THE IMS SHALL navigate the User to the Intern_Workspace for that Intern.
6. WHEN a User submits a valid new Intern form in the Department_View, THE IMS SHALL create a new Intern record assigned to that Department and display the new Intern in the list immediately upon successful creation.
7. WHEN a User submits an edit form for an existing Intern in the Department_View, THE IMS SHALL update the Intern record with the new values and reflect the updated values in the list immediately.
8. IF an edit form submission fails, THE IMS SHALL display an error message and preserve the previous Intern values in the list.
9. WHEN a User archives an Intern in the Department_View, THE IMS SHALL set the Intern's status to archived and remove the Intern from the active list immediately.
10. IF an archive action fails, THE IMS SHALL display an error message and retain the Intern in the active list.

---

### Requirement 4: Intern Workspace Navigation

**User Story:** As a User, I want to navigate between the 201 Profile, DTR Module, and Requirement Monitoring Module within an intern's workspace, so that I can access all intern data without losing context.

#### Acceptance Criteria

1. WHEN a User opens an Intern_Workspace and all sections load successfully, THE IMS SHALL display the 201_Profile as the default active section with the DTR_Module and Requirement_Module available as selectable tabs.
2. IF any section of the Intern_Workspace fails to load, THE IMS SHALL prevent access to the entire Intern_Workspace and display an error message.
3. WHEN a User selects a section tab in the Intern_Workspace, THE IMS SHALL display the selected section without navigating away from the Intern_Workspace.
4. WHILE a User is in the Intern_Workspace, THE IMS SHALL display the Intern's name and Department name as persistent identifiers visible on all three section tabs.

---

### Requirement 5: 201 Profile Management

**User Story:** As a User, I want to view and edit an intern's personal, academic, and internship-related information, so that the intern's profile remains accurate and up to date.

#### Acceptance Criteria

1. WHEN a User opens the 201_Profile section, THE IMS SHALL display all personal, academic, and internship-related fields for the Intern, including a profile photo field.
2. WHEN a User submits an updated 201_Profile form with all required fields present, THE IMS SHALL save the updated field values to the Intern record.
3. IF a 201_Profile form submission fails validation, THE IMS SHALL display field-level error messages and not save any changes.
4. WHEN a User uploads a profile photo in JPEG or PNG format not exceeding 5 MB, THE IMS SHALL replace the existing profile photo with the uploaded image.
5. IF a User uploads a file that is not JPEG or PNG or exceeds 5 MB, THE IMS SHALL display a validation error and reject the upload without modifying the existing profile photo.
6. WHEN a User confirms the archive action from the 201_Profile section, THE IMS SHALL set the Intern's status to archived.
7. WHEN a User requests a print or export of the 201_Profile, THE IMS SHALL generate a PDF document containing all of the Intern's profile fields and profile photo.

---

### Requirement 6: DTR Module — Attendance Recording

**User Story:** As a User, I want to manually input and manage daily attendance records for an intern, so that rendered hours and attendance metrics are accurately tracked.

#### Acceptance Criteria

1. WHEN a User opens the DTR_Module, THE IMS SHALL display all existing DTR_Entry records for the Intern in a spreadsheet-style table, ordered by date ascending.
2. WHEN a User adds a new DTR_Entry, THE IMS SHALL create a row with the specified date, time-in, and time-out values.
3. IF a User submits a new DTR_Entry with a missing date, time-in, or time-out value, THE IMS SHALL display a validation error identifying the missing field and reject the entry.
4. WHEN a DTR_Entry is saved with a time-out value later than the time-in value on the same calendar date, THE IMS SHALL automatically compute and display the Rendered_Hours as the difference between time-out and time-in, Overtime as any hours beyond 8 hours, and Undertime as any hours short of 8 hours for that entry.
5. WHEN a DTR_Entry is saved, THE IMS SHALL update the Intern's cumulative Rendered_Hours and Remaining_Hours.
6. IF a User submits a DTR_Entry where time-out is not later than time-in, THE IMS SHALL display a validation error and reject the entry without saving.
7. WHEN a User deletes a DTR_Entry, THE IMS SHALL remove the entry and recalculate the Intern's cumulative Rendered_Hours and Remaining_Hours.
8. WHEN a User applies a date range filter specifying a start date and end date in the DTR_Module, THE IMS SHALL display only DTR_Entry records whose dates fall within the start date and end date, inclusive.
9. WHEN a User requests an export of the DTR_Module with an active date range filter, THE IMS SHALL generate a document in the User's chosen format (PDF or CSV) containing only the DTR_Entry records currently visible under that filter.
10. WHEN a User requests an export of the DTR_Module with no active date range filter, THE IMS SHALL generate a document in the User's chosen format (PDF or CSV) containing all DTR_Entry records for the Intern.

---

### Requirement 7: Requirement Monitoring Module

**User Story:** As a User, I want to track the submission and approval status of required documents for each intern, so that compliance can be monitored and missing submissions identified.

#### Acceptance Criteria

1. WHEN a User opens the Requirement_Module, THE IMS SHALL display all active Requirement_Item records for the Intern, each showing its name, Requirement_Status (Pending, Submitted, or Approved), submission date, and remarks.
2. WHEN a User adds a new Requirement_Item with a name provided, THE IMS SHALL create the item with an initial Requirement_Status of Pending and a blank submission date and remarks.
3. WHEN a User updates the Requirement_Status of a Requirement_Item to Pending, Submitted, or Approved, THE IMS SHALL save the new status and record the UTC timestamp of the change.
4. WHEN a User uploads a file of an accepted type (PDF, JPEG, PNG, DOCX) not exceeding 10 MB to a Requirement_Item, THE IMS SHALL attach the file to that item and record the current date as the submission date.
5. IF a User uploads a file that is not an accepted type or exceeds 10 MB, THE IMS SHALL display a validation error and reject the upload without modifying the existing attachment.
6. WHEN a User adds or updates remarks on a Requirement_Item with text not exceeding 500 characters, THE IMS SHALL save the remarks to that item.
7. WHEN a User archives a Requirement_Item, THE IMS SHALL set the item to archived and remove it from the active Requirement_Module view.
8. WHEN a User requests a print or export of the Requirement_Module, THE IMS SHALL generate a formatted PDF document containing all active Requirement_Item records for the Intern, including each item's name, status, submission date, and remarks.

---

### Requirement 8: Archiving and Data Preservation

**User Story:** As a User, I want archived records to be preserved and retrievable, so that historical data is not permanently lost.

#### Acceptance Criteria

1. THE IMS SHALL preserve all archived Intern, DTR_Entry, and Requirement_Item records in storage without permanent deletion.
2. WHEN a User requests to view archived records and archived records exist, THE IMS SHALL display the archived records in a view separate from the active records list.
3. WHEN a User requests to view archived records and no archived records exist, THE IMS SHALL display an empty-state view with a message indicating that no archived records are available.
4. WHEN a User restores an archived Intern, THE IMS SHALL set the Intern's status back to Active, retain all associated DTR_Entry and Requirement_Item records, and include the Intern in the active Department_View list.
5. WHEN a User restores an archived Requirement_Item, THE IMS SHALL set the item's status back to its pre-archive Requirement_Status and include the item in the active Requirement_Module view.

---

### Requirement 9: Audit Trail

**User Story:** As an Admin, I want all significant actions to be logged automatically, so that changes are traceable and users are accountable.

#### Acceptance Criteria

1. WHEN a User creates, updates, archives, or restores any record in the IMS, or when a User logs in or logs out, THE IMS SHALL log an Audit_Trail entry containing the action type, affected record identifier, User identifier, and a UTC ISO 8601 timestamp.
2. THE IMS SHALL record Audit_Trail entries without requiring any action from the User.
3. WHEN an Admin requests to view the Audit_Trail, THE IMS SHALL display the log entries sorted by timestamp descending, filterable by date range, User, and action type.
4. IF the Audit_Trail cannot be retrieved due to a technical failure, THE IMS SHALL display an error message on the Audit_Trail view without exposing system internals.
5. WHEN an Admin applies filters to the Audit_Trail that match no entries, THE IMS SHALL display an empty-state message indicating that no log entries match the selected filters.

---

### Requirement 10: Top Navigation Bar

**User Story:** As a User, I want a consistent navigation bar across all pages, so that I can always identify my context and access session controls.

#### Acceptance Criteria

1. THE IMS SHALL display a top navigation bar on all authenticated pages containing the company logo, the application name, the authenticated User's name, and a logout button.
2. WHEN a User clicks the logout button in the navigation bar, THE IMS SHALL terminate the Session and redirect the User to the login page.
3. WHILE a User is navigating within the Dashboard → Department → Intern → Module hierarchy, THE IMS SHALL display breadcrumb navigation in the top navigation bar reflecting each level of the current location.
4. WHEN a User is on the Dashboard page, THE IMS SHALL display only "Dashboard" in the breadcrumb with no parent levels shown.
5. WHEN a User is on a page outside the defined Dashboard → Department → Intern → Module hierarchy, THE IMS SHALL omit the breadcrumb navigation from the top navigation bar.
