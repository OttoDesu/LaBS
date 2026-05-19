# Lab Booking Project Flowchart and Diagram

## Summary
This new flowchart and diagram reflect the actual reservation and lab booking flow in the project code, especially `booking.php`, `availability.php`, and `reservation-form.php`.

> Note: The attached `.docx` file was not available in the workspace, so direct verification of its diagrams was not possible. These diagrams are created from the project implementation.

---

## System User Types
- `Public User` — external users who can view lab availability and request lab reservations.
- `UTHM Student` — student users who can book labs and may require supervisor information for lab reservations.
- `UTHM Staff` — staff users with broader booking privileges and access to lab reservation features.
- `Cluster Admin` — manages clusters and can view cluster-level lab data.
- `Lab Supervisor` — oversees assigned labs and reviews bookings or manages lab resources.
- `Super Admin` / `Admin` — full system administrators who manage users, labs, clusters, assets, and system settings.

---

## System Modules
- `Booking Module` (`booking.php`) — presents lab selection, assigned labs, and cluster-level lab listings.
- `Availability Module` (`availability.php`) — displays lab calendars, approved time slots, and maintenance windows.
- `Reservation Module` (`reservation-form.php`) — collects booking details, validates the request, and stores reservations.
- `User Management` (`user-management.php`) — manages users, roles, and lab supervisor assignments.
- `Lab Management` (`lab-management.php`, `lab-management-supervisor.php`, `lab-management-cluster.php`) — manages lab records, clusters, and lab supervisors.
- `Asset Management` (`assets-management.php`, `assets-management-lab.php`) — manages lab assets and equipment listings.
- `Reporting / Analytics` (`report-analytics.php`, `super-admin-report-api.php`) — generates system reports and analytics for bookings and lab usage.
- `Profile` (`profile.php`, `profile_api.php`) — manages user profiles and personal details.
- `Authentication` (`index.php`, `logout.php`, `check_admin.php`) — handles login, session control, and access restrictions.

---

## User Reservation Flow

```mermaid
flowchart TD
    A[Start] --> B[Login]
    B --> C{User Role}
    C -->|Public / Student / Staff| D[Cluster Selection / Lab List]
    C -->|Lab Supervisor| E[Assigned Labs View]
    C -->|Admin / Super Admin| D
    D --> F[Select Lab]
    E --> F
    F --> G[Availability Page]
    G --> H{Selected Date}
    H -->|Under Maintenance| I[Show maintenance message]
    H -->|Available| J[Choose Time Slot]
    J --> K[Go to Reservation Form]
    K --> L{Booking Purpose}
    L -->|Class| M[Enter class details]
    L -->|Lab| N[Enter lab reservation details]
    N --> O[Optional equipment / chemicals]
    N --> P[Optional document upload]
    M --> Q[Form validation]
    O --> Q
    P --> Q
    Q -->|Errors| R[Show validation errors]
    Q -->|Valid| S[Insert booking and reservation records]
    S --> T[Success message / booking complete]
    R --> K
```

---

## Role-based User Activity Flowchart

Use the Mermaid code below in a Mermaid renderer (VS Code Mermaid preview, Mermaid Live Editor, or other Mermaid tools) to generate a diagram image.

```mermaid
flowchart LR
    subgraph Public / Warga UTHM
        P1((Login)) --> P2((Browse labs))
        P2 --> P3((Select lab & date))
        P3 --> P4((View availability))
        P4 --> P5((Submit reservation))
        P5 --> P6((Receive confirmation))
    end

    subgraph Lab Supervisor
        S1((Login)) --> S2((View assigned labs))
        S2 --> S3((Check bookings & availability))
        S3 --> S4((Approve / manage reservations))
        S4 --> S5((Update lab resources))
    end

    subgraph Lab Manager
        M1((Login)) --> M2((Manage labs & clusters))
        M2 --> M3((Review lab availability))
        M3 --> M4((Approve maintenance / bookings))
        M4 --> M5((Generate reports))
    end

    subgraph Admin
        A1((Login)) --> A2((Manage users & roles))
        A2 --> A3((Maintain system settings))
        A3 --> A4((Monitor bookings & analytics))
        A4 --> A5((Authorize admin operations))
    end

    P6 -.-> System[LaBS system handles booking data]
    S5 -.-> System
    M5 -.-> System
    A5 -.-> System
```

---

## System Component Diagram

```mermaid
flowchart LR
    User[User Roles]
    Web[Web Interface]
    DB[Database]
    User --> Web
    Web -->|Login & Session| Auth[Authentication / init.php]
    Web -->|Display labs| booking.php
    booking.php --> DB
    Web -->|View availability| availability.php
    availability.php --> DB
    Web -->|Submit reservation| reservation-form.php
    reservation-form.php --> DB

    subgraph Database Tables
        Labs[labs]
        Clusters[clusters]
        Bookings[lab_bookings]
        Reservations[lab_reservations]
        Equip[reservation_equipment]
        Chems[reservation_chemicals]
        Users[users]
        Assets[assets]
        SupervisorScope[lab_supervisor_labs]
    end

    DB --> Labs
    DB --> Clusters
    DB --> Bookings
    DB --> Reservations
    DB --> Equip
    DB --> Chems
    DB --> Users
    DB --> Assets
    DB --> SupervisorScope

    Web -->|Admin / user mgmt| user-management.php
    user-management.php --> SupervisorScope
    user-management.php --> Users
    Web -->|Manage labs| lab-management.php
    lab-management.php --> Labs
    lab-management.php --> Clusters
```

---

## Context Diagram (CD)

```mermaid
flowchart LR
    Admin[Administrator]
    Public[Public / UTHM User]
    Supervisor[Lab Supervisor]
    Manager[Lab Manager]
    System[Lab Booking System (LaBS)]
    Auth[Authentication / Session]
    DB[Database]

    Admin -->|Login, manage users/roles, review booking status, generate reports| System
    Public -->|Register/login, view lab availability, request bookings, view booking status| System
    Supervisor -->|Login, view assigned labs, review bookings, manage lab details| System
    Manager -->|Login, manage labs/clusters, approve maintenance, monitor lab status| System

    System -->|Authenticate/session| Auth
    System -->|Store/retrieve booking data| DB
    System -->|Store/retrieve user data| DB
    System -->|Store/retrieve lab data| DB
    System -->|Generate report data| DB
```

---

## Data Flow Diagram (DFD)

```mermaid
flowchart TD
    User[User]
    Browse[Browse labs / select date]
    Check[Check availability]
    Reserve[Submit reservation request]
    Validate[Validate reservation]
    Confirm[Booking confirmation]
    Labs[labs / clusters]
    Bookings[lab_bookings]
    Reservations[lab_reservations]
    Equip[reservation_equipment]
    Chems[reservation_chemicals]

    User -->|Choose lab/date| Browse
    Browse -->|Request lab list| Check
    Check -->|Query lab availability| Labs
    Labs -->|Return availability| Check
    Check -->|Available slot| Reserve
    Reserve -->|Reservation details| Validate
    Validate -->|Valid request| Bookings
    Validate -->|Valid request| Reservations
    Validate -->|Valid request| Equip
    Validate -->|Valid request| Chems
    Validate -->|Invalid request| User
    Bookings -->|Store booking| Bookings
    Reservations -->|Store reservation details| Reservations
    Equip -->|Store equipment items| Equip
    Chems -->|Store chemical items| Chems
    Validate -->|Send confirmation| Confirm
    Confirm -->|Display result| User
```

---

## DFD Levels

### Level 0 — Top-Level Booking System
```mermaid
flowchart TD
    Public[Public / UTHM User]
    Supervisor[Lab Supervisor]
    Admin[Admin / Super Admin]
    LaBS[0: Lab Booking System]
    Users[User Data Store]
    Labs[Lab Data Store]
    Bookings[Booking Data Store]

    Public -->|Account credentials, booking request, view availability| LaBS
    Supervisor -->|Login, assigned lab review, booking status| LaBS
    Admin -->|Login, user management, report request| LaBS

    LaBS -->|Read / update user records| Users
    LaBS -->|Read / update lab records| Labs
    LaBS -->|Read / write booking/reservation records| Bookings

    Users -->|Provide user details| LaBS
    Labs -->|Provide lab availability / maintenance data| LaBS
    Bookings -->|Provide booking status / history| LaBS
```

### Level 1 — Booking and Availability
```mermaid
flowchart TD
    User[User] -->|Choose lab| Booking[Booking Module]
    Booking -->|Fetch cluster & lab list| Labs[labs + clusters]
    Booking -->|Open calendar| Availability[Availability Module]
    Availability -->|Read approved slots| Bookings[lab_bookings]
    Availability -->|Read maintenance details| Labs
    Availability -->|Show available slots| User
```

### Level 1 — Reservation Form Process
```mermaid
flowchart TD
    User[User] -->|Submit reservation request| Reservation[Reservation Form]
    Reservation -->|Validate user data| Validation[Validation Logic]
    Reservation -->|Get lab details| Labs[labs]
    Validation -->|Return errors| Reservation
    Validation -->|Proceed on valid data| BookingRecord[Booking Record Creation]
    BookingRecord -->|Store booking| Bookings[lab_bookings]
    BookingRecord -->|Store reservation| Reservations[lab_reservations]
```

### Level 2 — Reservation Record Creation
```mermaid
flowchart TD
    Reservation[Reservation Form] -->|Prepare booking data| BookingsProcess[Booking Insert]
    Reservation -->|Prepare reservation data| ReservationsProcess[Reservation Insert]
    Reservation -->|Prepare equipment list| EquipmentProcess[Equip Insert]
    Reservation -->|Prepare chemical list| ChemicalProcess[Chemical Insert]

    BookingsProcess -->|Insert record| Bookings[lab_bookings]
    ReservationsProcess -->|Insert record| Reservations[lab_reservations]
    EquipmentProcess -->|Insert each item| Equip[reservation_equipment]
    ChemicalProcess -->|Insert each item| Chems[reservation_chemicals]
```

---

## Key Process Details

- `booking.php` shows available labs by cluster, or assigned labs for supervisors.
- `availability.php` shows a lab calendar with approved booked time slots and maintenance windows.
- `reservation-form.php` validates all input, protects against maintenance conflicts, and stores:
  - `lab_bookings`
  - `lab_reservations`
  - `reservation_equipment`
  - `reservation_chemicals`
- The form has two booking purposes:
  - `class` booking: requires course code, subject name, class section; no equipment/chemicals; UTHM affiliation assumed.
  - `lab` reservation: requires title and activity details; optional equipment/chemicals; student bookings may require supervisor contact.

---

## Recommended Alignment Notes

- If the `.docx` flowchart shows a simple "select lab → book" path, it should also include the maintenance check and purpose-specific validation.
- If it does not include separate handling for `class` vs `lab` bookings, add that branch.
- If it does not include `reservation_equipment` / `reservation_chemicals` as separate insertion points, that is missing from the actual project flow.

---

## Use
Open `project-flowchart-and-diagram.md` in VS Code to view the Mermaid diagrams and compare them to your document.
