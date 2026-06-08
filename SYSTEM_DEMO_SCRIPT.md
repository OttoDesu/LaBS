# LaBS System Demo Script

## Introduction
This document provides a complete demo script for the Laboratory Booking System (LaBS) across all main user roles. Each section is written in English and covers the full flow from login/registration through to primary usage scenarios.

---

## 1. Public User Flow

### 1.1 Purpose
The Public User flow demonstrates how an external user registers, logs in, books a laboratory, views booking history, and updates profile information.

### 1.2 Demo Steps

1. Open the browser and navigate to the project homepage.
2. Click the "Sign Up" button or go to `signup.php`.
3. Demonstrate the registration form:
   - Enter a full name.
   - Enter a 12-digit IC number.
   - Enter a valid email address.
   - Enter a secure password with at least 8 characters, one uppercase letter, and one number.
   - Confirm the password.
4. Click the "Sign Up" button.
5. Show the success message and redirect to the login page.
6. On the login page, select "Public User" if needed.
7. Enter the registered email and password.
8. Click "Login" and confirm redirection to the public dashboard.
9. Navigate to the booking page, `booking.php`.
10. Select a laboratory from the dropdown list.
11. Show the laboratory details: capacity, assets, supervisor information.
12. Explain the calendar interface and the use of default date selection.
13. Select an available booking date.
14. Show available time slots for that date.
15. Choose a time slot and enter group size.
16. Optionally add booking notes.
17. Click "Confirm Booking."
18. Show the booking confirmation message and status as "pending." 
19. Navigate to the booking history page, `booking-details.php`.
20. Demonstrate the booking list, search, and filter functionality.
21. Show the booking status and action buttons such as View/Print, Add to Google Calendar, or Cancel.
22. Return to `profile.php`.
23. Show the profile page with personal information.
24. Click "Edit Profile" and update the contact number.
25. Save the changes and show the confirmation message.

### 1.3 Key Notes
- Emphasize that public users must sign up to create an account.
- Verify that profile updates are saved securely.
- Note that public users cannot access administrative or supervisor functions.

---

## 2. Warga UTHM Flow (Student + Staff)

### 2.1 Purpose
This flow covers UTHM users who log in through institutional credentials and use the same booking and history features with Warga UTHM validation.

### 2.2 Demo Steps

1. Open the login page `index.php` or the main login screen.
2. Select "Warga UTHM" as the login type.
3. Enter a valid UTHM email address and password.
   - Example: `student.123@uthm.edu.my` or `staff.123@staff.uthm.edu.my`.
4. Click "Login" and confirm redirection to the UTHM dashboard.
5. Show the dashboard or homepage relevant to Warga UTHM.
6. Navigate to the booking page.
7. Select a laboratory, then explain how lab availability is displayed.
8. Select a booking date and time slot.
9. Add any required booking details (group size, notes).
10. Submit the booking and show success confirmation.
11. Navigate to the booking history page.
12. Use the search bar and filter by status to find specific bookings.
13. Show how the user can view booking details and cancel if permitted.
14. Open `profile.php`.
15. Show personal information fields like name, email, IC number, and contact.
16. Edit profile details if allowed and save.
17. Confirm that the profile update is successful.

### 2.3 Key Notes
- Explain the difference between Warga UTHM and public user paths.
- Highlight that institutional users can access UTHM-specific booking workflows.
- Mention that UTHM student and staff are grouped as "Warga UTHM" for demo purposes.

---

## 3. Lab Supervisor Flow

### 3.1 Purpose
This flow shows the Lab Supervisor role handling booking approvals, lab assignment, supervisor-specific booking history, and lab availability.

### 3.2 Demo Steps

1. Log in as a Lab Supervisor using `index.php`.
2. Verify that the login is successful and the supervisor dashboard appears.
3. Navigate to the booking management page.
4. Show the pending bookings list for assigned laboratories.
5. Select a booking request and open its details.
6. Review booking details: user name, lab, date, time, purpose.
7. Click "Approve" for a valid booking.
8. Demonstrate the email notification or success message.
9. Show the booking status changing to "Approved."
10. Select another booking and click "Reject."
11. Enter a rejection reason and save.
12. Confirm that the rejected booking updates and notifies the user.
13. Show the supervisor-only lab list, limited to assigned labs.
14. Visit the asset or lab schedule section if available.
15. Demonstrate the lab supervisor’s ability to view assigned assets and maintenance schedules.
16. Navigate to the profile page and confirm supervisor details.

### 3.3 Key Notes
- Explain that supervisors only see labs they manage.
- Emphasize booking approval/rejection workflow.
- Show that supervisors cannot access cluster-wide or global admin functions.

---

## 4. Cluster Admin Flow

### 4.1 Purpose
This flow demonstrates Cluster Admin functions, including cluster-specific user management, lab oversight, and reports for cluster operations.

### 4.2 Demo Steps

1. Log in as a Cluster Admin using `index.php`.
2. Verify successful login and cluster admin dashboard.
3. Navigate to the user management page if accessible.
4. Show that user lists are limited to the cluster.
5. Filter users by role within the cluster.
6. Navigate to lab management or cluster asset management.
7. Show labs in the cluster and their current statuses.
8. Demonstrate creating or editing a lab configuration within the cluster.
9. Navigate to booking reports and analytics for the cluster.
10. Apply filters by date, lab, or booking status.
11. Show the report results, summary data, and charts if available.
12. Demonstrate how cluster admin can manage cluster maintenance schedules.
13. Return to profile or dashboard and confirm cluster admin role details.

### 4.3 Key Notes
- Highlight cluster-level restrictions and permissions.
- Emphasize cluster admin’s ability to oversee labs, users, and reports only within their cluster.

---

## 5. Admin Flow (Super Admin)

### 5.1 Purpose
This flow covers the full system administrator capabilities, including complete user management, global lab administration, and system-wide reports.

### 5.2 Demo Steps

1. Log in as the Super Admin using `index.php`.
2. Confirm the admin dashboard loads with full system access.
3. Navigate to `user-management.php`.
4. Show the full list of users across all clusters and roles.
5. Add a new user as a public user, Warga UTHM user, or lab supervisor.
6. Demonstrate user role selection and cluster assignment.
7. Save the new user and confirm success.
8. Edit an existing user’s details and save updates.
9. Navigate to lab management and show all labs across clusters.
10. Create or update a laboratory entry, including capacity and schedule.
11. Navigate to asset management and show equipment inventory across labs.
12. Open the analytics/report page.
13. Select a time range and cluster, then generate a system-wide report.
14. Show charts, summary scores, and data export options.
15. Navigate to settings or support if available.
16. Show logout behavior and end the admin session.

### 5.3 Key Notes
- Emphasize full system control available only to Super Admin.
- Show the difference between admin access and cluster admin access.
- Highlight data visibility across clusters and roles.

---

## 6. General Demo Notes

### 6.1 System Requirements
- PHP with MySQL support
- Local development environment such as XAMPP
- Browser with JavaScript enabled

### 6.2 Demo Preparation
- Use test accounts for each role.
- Ensure the database contains sample labs, clusters, and users.
- Have at least one pending booking available for supervisor approval.
- Prepare a cluster admin account with one cluster assigned.
- Prepare a super admin account with access to all clusters.

### 6.3 Demonstration Tips
- Start with the public user flow to show end-user experience.
- Then move to Warga UTHM flows to highlight institutional login.
- Follow with Lab Supervisor to show approval workflow.
- Finish with Cluster Admin and Super Admin for management and analytics.
- Show login, booking, history, profile, approval, and reporting in sequence.
- Keep the demo interactive and explain each user’s permissions clearly.

---

## 7. Summary
This demo script provides a complete end-to-end walkthrough of the LaBS system for each key user role. Use it to present the system functionality in a structured way, ensuring each user path is clearly explained and demonstrated.