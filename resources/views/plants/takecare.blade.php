@component('mail::message')
# One of your plants require watering and/or fertilizing!
<img src={{$email->avatar}} class="img-responsive" style="display:block;margin-left: auto;margin-right: auto;width:250px;height:auto;" alt="Plant image">

@component('mail::button', ['url' => URL::to('plants/' . $email->id)])
Visit it!
@endcomponent

Thank you for being so nice to your plants,<br>
{{ config('app.name') }} 🌸
@endcomponent
