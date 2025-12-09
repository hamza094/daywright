<x-mail::message>

Dear valued user,

We would like to inform you that your password has been changed successfully at {{$time}} UTC Timezone. If you did not perform this action, we kindly advise you to contact our customer department immediately at daywright@gmail.com for further assistance.

<x-mail::button :url="$url">
DayWright
</x-mail::button>

Thank you for choosing our services,<br>
{{ config('app.name') }}
</x-mail::message>
