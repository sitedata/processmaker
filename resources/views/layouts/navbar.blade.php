<nav class="navbar navbar-expand-lg navbar-light bg-light p-0">
    <ul class="navbar-nav mr-auto">
    @php
    $menu = Menu::get('topnav');
    @endphp
    @foreach($menu->items as $item)
      <li class="nav-item">
        <a class="nav-link" href="{{ $item->url() }}">{{$item->title}}</a>
      </li>
    @endforeach
    </ul>


    <component id="navbar-request-button" v-bind:is="'request-modal'"></component>

    <span class="navbar-text notifications">
      <i class="fas fa-bell" aria-hidden="true"></i>
    </span>
    <ul class="navbar-nav">
      <li class="break"></li>
      <li class="dropdown">
        <img class="avatar dropdown-toggle " id="navbarDropdown" src="/img/avatar.png" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
           <a class="dropdown-item drop-header"><img class="avatar-small" src="/img/avatar.png">{{__('John Bunton')}}</a>
           @foreach($dropdown_nav->items as $row)
              <a class="dropdown-item" href="{{ url($row->link->path['route']) }}"><i class="fas {{$row->attr('icon')}} fa-fw fa-lg"></i>{{$row->title}}</a>
           @endforeach
         </div>
      </li>
    </li>
  </ul>
</nav>
