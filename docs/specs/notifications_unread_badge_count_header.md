# Feature: Notifications unread badge/count in header

Goal  
Display a clear unread notifications count for authenticated users in the header so they can see pending activity at a glance.

Behavior  
When an authenticated user loads pages that include the main header, the UI shows a notifications indicator with the current unread count sourced from existing notification data.  
For users with no unread notifications, behavior is consistent and explicit (either hidden badge or visible zero), and remains the same across pages.  
Unauthenticated users do not see authenticated notification count UI.

Constraints  
Follow current MVC boundaries: retrieval and aggregation stay in controller/service/repository layers, while the header view only renders data it receives.  
Do not introduce in-app notifications list behavior in this feature; this issue is count/badge only.  
Use existing authentication/session context and notification persistence; avoid schema or workflow changes unless strictly required.  
Keep UI changes minimal and consistent with current header styling/accessibility patterns.

Success Criteria  
Authenticated users see an unread notifications count in the header.  
Count reflects current unread notification state from existing data flow.  
No regression for unauthenticated navigation/header behavior.  
Feature remains scoped to badge/count only, with list/read-state behavior left to follow-up issues.

## Implementation Plan

1 add a repository query method to count unread notifications for a given user id
2 add a service method that returns the unread count using the repository count method
3 wire unread notification count into the authenticated page rendering context used by the header
4 render a notifications badge/count in the header using the injected count and defined zero-state behavior
5 add/adjust targeted tests to verify authenticated count display, zero-state behavior, and unauthenticated header behavior
