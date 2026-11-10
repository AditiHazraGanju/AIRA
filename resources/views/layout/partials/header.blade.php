<nav class="navbar">
  <div class="navbar-content">
    <div class="logo-mini-wrapper">
      <img src="{{ url('assets/images/logo-light.png') }}" class="logo-mini logo-mini-light" style="width: 50px; height: auto;" alt="logo">
      <img src="{{ url('assets/images/logo-dark.png') }}" class="logo-mini logo-mini-dark" style="width: 50px; height: auto;" alt="logo">
    </div>
    <ul class="navbar-nav">
      <li class="theme-switcher-wrapper nav-item">
        <input type="checkbox" value="" id="theme-switcher">
        <label for="theme-switcher">
          <div class="box">
            <div class="ball"></div>
            <div class="icons">
              <i class="link-icon" data-lucide="sun"></i>
              <i class="link-icon" data-lucide="moon"></i>
            </div>
          </div>
        </label>
      </li>
    </ul>

    <a href="#" class="sidebar-toggler">
      <i data-lucide="menu"></i>
    </a>

  </div>
</nav>
