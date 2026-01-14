# SmartVolt Application Overview

SmartVolt is a smart energy management system designed to help users monitor and control their electronic devices' energy consumption. Users can register their devices, organize them into groups, and track their power usage in real-time. The application provides detailed analytics, allows users to set consumption goals, and enables the creation of automated routines to manage devices efficiently, promoting energy savings and smarter home management.

---

## Backend Requirements

### 1. Authentication
- **User Login:** Secure user authentication to access the system.
- **User Registration:** Allow new users to create an account.

### 2. Dashboard
- **Device Groups Overview:** Display a summary of all device groups.
- **Group Status & Goals:** Show the current status of each group relative to its defined usage goals.
- **Consumption Graphs:** Provide visualizations of energy consumption for different groups.

### 3. Group Management
- **Group CRUD:** Allow users to create, read, update, and delete device groups. This includes adding/removing devices from a group and editing the group's name.
- **Detailed Information:** Show detailed energy consumption data for each group.

### 4. Device Management
- **Device CRUD:** Allow users to manage their devices.
- **Device Linking:** Users must be able to link a new device to their account, for example, by using a unique identifier (e.g., from a QR code).

### 5. Customizations
- **Routine Creation:** Enable users to create, configure, and manage routines that automate device actions (e.g., turning a device on/off at a specific time).
- **Usage Goals:** Allow users to configure and manage specific energy usage goals for individual devices or groups.

---

## Backend Implementation Details

This section outlines the existing backend structure, including API endpoints and core data flows.

### API Routes

All API routes are prefixed with `/api`. Authentication is handled via Sanctum, requiring an API token for protected endpoints.

#### Authentication
- `POST /login`: Authenticates a user and returns an API token.
- `POST /register`: Creates a new user account.

#### Dashboard
- `GET /dashboard/summary`: Retrieves a summary of device and group statuses for the authenticated user.
- `GET /dashboard/consumption-history`: Retrieves historical consumption data for dashboard charts.
- `GET /dashboard/voltage-history`: Retrieves historical voltage data for dashboard charts.

#### Devices
- `GET /devices`: Lists all devices belonging to the user.
- `GET /devices/{device}`: Shows details for a specific device.
- `PUT /devices/{device}`: Updates a specific device's information (e.g., name).
- `DELETE /devices/{device}`: Deletes a device.
- `POST /devices/link`: Links a new physical device to the user's account using its unique hardware ID.

#### Groups
- `GET /groups`: Lists all of the user's device groups.
- `POST /groups`: Creates a new device group.
- `GET /groups/{group}`: Shows details for a specific group, including its devices.
- `PUT /groups/{group}`: Updates a group (e.g., changes its name, adds/removes devices).
- `DELETE /groups/{group}`: Deletes a group.

#### Routines
- `GET /routines`: Lists all routines configured by the user.
- `POST /routines`: Creates a new routine.
- `GET /routines/{routine}`: Shows details for a specific routine.
- `PUT /routines/{routine}`: Updates a routine.
- `DELETE /routines/{routine}`: Deletes a routine.

#### Usage Goals
- `GET /usage-goals`: Lists all usage goals set by the user.
- `POST /usage-goals`: Creates a new usage goal for a device or group.
- `GET /usage-goals/{usage_goal}`: Shows details for a specific usage goal.
- `PUT /usage-goals/{usage_goal}`: Updates a usage goal.
- `DELETE /usage-goals/{usage_goal}`: Deletes a usage goal.

#### IoT Data
- `POST /iot/report`: Endpoint for physical IoT devices to report their data (e.g., energy consumption, status). This route is typically not called by the frontend client directly.

### Core Creation Flows

#### 1. User Registration
- A `POST` request is made to `/api/register` with `name`, `email`, and `password`.
- The `RegisterController` validates the data using `RegisterRequest` and creates a new `User` record.
- Upon success, a user is created. The client can then call `/api/login` to get an auth token.

#### 2. Device Linking
- The user obtains a unique identifier from the physical device (e.g., via QR code).
- A `POST` request is made to `/api/devices/link` with the `hardware_id`.
- The `DeviceController` finds the device with that `hardware_id` and assigns the authenticated user's ID to it, linking the device to the account.

#### 3. Group Creation
- A `POST` request is made to `/api/groups`.
- The body includes a `name` for the group and an array of `device_ids` to be included.
- The `GroupController` creates a new `Group` and attaches the specified devices to it.

#### 4. Routine Creation
- A `POST` request is made to `/api/routines`.
- The request body contains the `device_id` or `group_id` the routine applies to, the `action` (e.g., "turn_on", "turn_off"), and the `scheduled_time`.
- The `RoutineController` creates a new `Routine` record. A scheduled job (`ProcessRoutines`) runs periodically to execute these routines.

#### 5. Usage Goal Creation
- A `POST` request is made to `/api/usage-goals`.
- The request body includes the `target_type` ('device' or 'group'), the `target_id`, the `value` (e.g., in kWh), and the `period` (e.g., 'daily', 'monthly').
- The `UsageGoalController` creates the `UsageGoal` record.
