<link href="https://cdn.jsdelivr.net" rel="stylesheet">


<div class="container">
  <table class="table table-striped table-bordered table-hover">
    <thead>
    <tr>
            @foreach ($coulmns as $value)

                <th>{{ App\Models\Assessment::where('name', $value)->first()['hint'] }}</th>

            @endforeach

        </tr>  
    
    </thead>
    <tbody>
     @foreach($building as $value)
            <tr>
                @foreach ($coulmns as $col)
                    <td>{{ $value->$col }}</td>

                @endforeach

            </tr>
        @endforeach
    </tbody>
  </table>
</div>
