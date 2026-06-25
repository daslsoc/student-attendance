@component('mail::message')
# Temporary Login Link

Click the button below to login to the School Attendance system:

@component('mail::button', ['url' => $loginLink])
Login
@endcomponent

## Next Steps
1) Select the subject and class and click Continue
2) The registered students will be presented. Simply click on the student name and their name will turn green.
3) Once all students have been entered, click Submit Attendance.

Note that this link will expire after sometime. If the link expires please request another login link.

Thanks,<br>
{{ config('custom.management_team_name') }}
@endcomponent
