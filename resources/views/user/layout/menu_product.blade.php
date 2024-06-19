<section class="hero hero-normal">
    <div class="container">
        <div class="row">
        <div class="col-lg-3">
				<div class="hero__categories">
					<div class="hero__categories__all">
						<i class="fa fa-bars"></i>
						<span>@lang('lang.cate')</span>
					</div>
					<ul class="submenu-list">
						@foreach($categories as $cat)
						<li class="submenu-list_item"><a href="/categories/{!! $cat['id'] !!}">{!! $cat['name'] !!}</a></li>
						<div class="item-show">
							<div class="item-show_item">
								<p class="item-show_item-head">
								@lang('lang.subcate')
								</p>
								<p class="item-show_item-content">
									@foreach($cat['Subcategories'] as $sub)
									@if($sub['active'] == 1)
									<a href="/subcategories/{!! $sub['id'] !!}">{!! $sub['name'] !!}</a>
									<br>
									@endif
									@endforeach
								</p>
							</div>
						</div>
						@endforeach
					</ul>
				</div>
			</div>
            <div class="col-lg-9">
                <div class="hero__search">
                    <div class="hero__search__form">
                        <form action="/search" >
                        <a href="/all_products">
                            <div class="hero__search__categories">
                                @lang('lang.all') @lang('lang.products')
                            </div>
                        </a>
                            <input type="search" name="search" value="{!! Request::get('search') !!}" placeholder="@lang('lang.search')...">
                            <button type="submit" class="site-btn">@lang('lang.search')</button>
                        </form>
                    </div>
                    <div class="hero__search__phone">
                        <div class="hero__search__phone__icon">
                            <i class="fa fa-phone"></i>
                        </div>
                        <div class="hero__search__phone__text">
                            <h5>{!! ($about!=null)?$about['phone']:'' !!}</h5>
                            <span>{!! ($about!=null)?$about['worktime']:'' !!}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
	var listItems = document.querySelectorAll('.submenu-list_item');
	var itemShows = document.querySelectorAll('.item-show');

	listItems.forEach((item, index) => {
		const itemShow = itemShows[index];

		item.addEventListener('mouseenter', function() {
			if (document.querySelector('.item-show.active') && document.querySelector('.submenu-list_item.active')) {
				document.querySelector('.item-show.active').classList.remove('active');
				document.querySelector('.submenu-list_item.active').classList.remove('active');
			}
			itemShow.classList.add('active');
			item.classList.add('active');

		})

	})
</script>