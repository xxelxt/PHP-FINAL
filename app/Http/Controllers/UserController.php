<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use App\Models\User;
use App\Models\About;
use App\Models\Brands;
use App\Models\Rating;
use App\Models\Banners;
use App\Models\Products;
use App\Models\Wishlist;
use App\Models\Discounts;
use App\Models\Orders;
use App\Models\Orders_Detail;
use App\Models\Categories;
use Illuminate\Support\Str;
use App\Models\Imagelibrary;
use Illuminate\Http\Request;
use App\Models\SubCategories;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class UserController extends Controller
{
    // Phương thức khởi tạo để chia sẻ dữ liệu chung qua các view
    function __construct()
    {
        // Lấy dữ liệu từ các model khác nhau và chia sẻ chúng với các view
        $user = User::all();
        $subcategories = SubCategories::where('active', 1)->orderBy('id', 'ASC')->get();
        $categories = Categories::where('active', 1)->orderBy('id', 'ASC')->get();
        $discounts = Discounts::all();
        $products = Products::where('active', 1)->orderBy('id', 'ASC')->get();
        $banners = Banners::where('active', 1)->orderBy('id', 'ASC')->get();
        $about = About::find(1);
        $brands = Brands::where('active', 1)->orderBy('id', 'ASC')->get();
        $image = Imagelibrary::all();
        $new_products = Products::get()->where('active', 1)->sortByDesc('created_at')->take(10);
        $wishlist = new Wishlist;
        view()->share('about', $about);
        view()->share('banners', $banners);
        view()->share('brands', $brands);
        view()->share('products', $products);
        view()->share('discounts', $discounts);
        view()->share('categories', $categories);
        view()->share('subcategories', $subcategories);
        view()->share('user', $user);
        view()->share('image', $image);
        view()->share('new_products', $new_products);
        view()->share('wishlist', $wishlist);
    }
    // Phương thức để hiển thị trang chủ
    public function home()
    {
        return view('user.pages.home');
    }

    public function get_login()
    {
        if (Auth::check()) {
            return redirect('/'); // Chuyển hướng về trang chủ nếu đã đăng nhập
        }
        return view('user.login');
    }

    // Phương thức để hiển thị form đăng ký người dùng
    public function get_register()
    {
        if (Auth::check()) {
            return redirect('/'); // Chuyển hướng về trang chủ nếu đã đăng nhập
        }
        return view('user.register');
    }

    public function forgetpassword()
    {
        $about = About::first();
        if (Auth::check()) {
            return redirect('/'); // Chuyển hướng về trang chủ nếu đã đăng nhập
        }
        return view('user.forgetpassword');
    }

    public function update_new_pass()
    {
        if (Auth::check()) {
            return redirect('/'); // Chuyển hướng về trang chủ nếu đã đăng nhập
        }
        return view('user.update_new_pass');
    }

    // Phương thức để hiển thị trang danh sách người dùng (chỉ có quản trị viên mới được truy cập)
    public function list()
    {
        // Lấy và chuyển dữ liệu người dùng đến view
        $users = User::with('roles', 'permissions')->get();
        return view('admin.user.list', [
            'users' => $users
        ]);
    }
    //     public function index()
    //     {
    //         $user_id = Auth::user()->id;
    //         $data = Auth::user()->roles;
    //         dd($data);
    //         // $arrPermission = [];
    //         // foreach($data as $value) $arrPermission[] = $value->name;
    //         // $collection = new Collection($arrPermission);
    //         // dd($collection->contains("all_product"));
    //  }

    // Phương thức để xóa một nhân viên (chỉ có quản trị viên mới được thực hiện)
    public function delete_staff($id)
    {
        $user = User::find($id);
        if ($user['active'] == 0) {
            if ($user->hasRole('admin')) {
                return response()->json(['error' => "Can't delete admin account"]);
            } else {
                $user->delete($id);
                if ($user['image'] != 'avatar.jpg') {
                    File::delete('upload/avatar/' . $user['image']);
                }
                return response()->json(['success' => 'Delete Successfully']);
            }
        } else {
            return response()->json(['error' => "Can't delete because Status being activated "]);
        }
    }

    // Phương thức để xóa một đánh giá
    public function delete_rating($id)
    {
        $rating = Rating::find($id);
        $rating->delete($id);
        return response()->json(['success' => 'Delete Successfully']);
    }

    // Phương thức để xử lý việc đăng ký người dùng
    public function post_register(Request $request)
    {
        // Xác thực dữ liệu đăng ký người dùng và tạo một người dùng mới
        $request->validate([
            'firstname' => 'required|min:1',
            'lastname' => 'required|min:1',
            'username' => 'required|unique:users',
            'email' => 'required|unique:users',
            'password' => 'required',
            'passwordagain' => 'required|same:password',
        ], [
            'firstname.required' => 'Firstname is required',
            'lastname.required' => 'Lastname is required',
            'username.required' => 'Username is required',
            'username.unique' => 'Username already exists',
            'email.required' => 'Email is required',
            'email.unique' => 'Email already exists',
            'password.required' => 'Password is required',
            'passwordagain.required' => 'Password is required',
            'passwordagain.same' => "Password doesn't match",
        ]);
        $request['password'] = bcrypt($request['password']);
        $request['image'] = 'avatar.jpg';
        $user = User::create($request->all());
        $user->syncRoles('user');
        return redirect('login')->with('thongbao', 'Sign up successfully');
    }

    // Phương thức để xử lý việc đăng nhập người dùng
    public function post_login(Request $request)
    {
        $request->validate([
            'login' => 'required', // Trường "login" sẽ chấp nhận cả email và username
            'password' => 'required'
        ], [
            'login.required' => 'Vui lòng nhập email hoặc tên người dùng',
            'password.required' => 'Vui lòng nhập mật khẩu'
        ]);

        $login = $request->input('login');

        // Kiểm tra xem giá trị nhập vào là email hay username
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Thử đăng nhập bằng trường tương ứng
        if (Auth::attempt([$fieldType => $login, 'password' => $request->password])) {
            Cookie::queue(Cookie::forget('cart'));
            return redirect('/');
        } else {
            return redirect('/login')->with('canhbao', 'Đăng nhập không thành công');
        }
    }

    // phương thức log out
    public function logout()
    {
        Auth::logout();
        Cart::destroy();
        return redirect('/'); // di chuyển về trang đăng nhập
    }

    // Phương thức để hiển thị trang hồ sơ người dùng
    public function profile()
    {
        // Hiển thị trang hồ sơ người dùng
        if (Auth::check()) {
            $user = Auth()->user();
        } else {
            return redirect('/login');
        }
        return view('user.profile', ['user' => $user]);
    }

    // Phương thức để hiển thị trang chi tiết sản phẩm
    public function product_deltails($id)
    {
        // Lấy và hiển thị chi tiết sản phẩm cùng với các sản phẩm liên quan và đánh giá
        $pro = Products::all();
        $products = Products::find($id);

        $related_products = Products::where('active', 1)
            ->where('categories_id', $products['categories_id'])
            ->where('id', '!=', $id)
            ->inRandomOrder()
            ->take(4)
            ->get();

        $wishlist = new Wishlist;
        $countWishlist = $wishlist->countWishlist($products['id']);
        $ratings = Rating::where('products_id', $id)->orderBy('id', 'DESC')->get();
        // dd($ratings);
        // die;
        return view('user.pages.product_details', [
            'products' => $products,
            'related_products' => $related_products, 'countWishlist' => $countWishlist,
            'pro' => $pro, 'ratings' => $ratings
        ]);
    }

    // Phương thức để hiển thị trang lưới sản phẩm theo danh mục
    public function product_grid($id)
    {
        // Hiển thị trang lưới sản phẩm theo danh mục
        $danhmuc = Categories::find($id);
        $categories = Categories::all();

        // Thêm điều kiện lọc và sắp xếp giống như hàm product_all
        $query = Products::where('active', 1)->where('categories_id', $id);

        if (request('sort') == 'price_asc') {
            $query->orderBy('price', 'ASC');
        } elseif (request('sort') == 'price_desc') {
            $query->orderBy('price', 'DESC');
        } elseif (request('sort') == 'name_asc') {
            $query->orderBy('name', 'ASC');
        } elseif (request('sort') == 'name_desc') {
            $query->orderBy('name', 'DESC');
        } else {
            $query->orderBy('id', 'ASC');
        }

        $products = $query->paginate(12);
        $count = $products->total(); // Sử dụng total() để lấy tổng số sản phẩm

        $wishlist = new Wishlist;

        return view('user.pages.product_grid', [
            'danhmuc' => $danhmuc,
            'categories' => $categories,
            'products' => $products,
            'count' => $count,
            'wishlist' => $wishlist
        ]);
    }

    // Phương thức để hiển thị trang lưới sản phẩm theo danh mục phụ
    public function product_grid_sub($id)
    {
        // Hiển thị trang lưới sản phẩm theo danh mục phụ
        $danhmuc = SubCategories::find($id);
        $categories = Categories::all();

        // Thêm điều kiện lọc và sắp xếp giống như hàm product_all
        $query = Products::where('active', 1)->where('sub_id', $id);

        if (request('sort') == 'price_asc') {
            $query->orderBy('price', 'ASC');
        } elseif (request('sort') == 'price_desc') {
            $query->orderBy('price', 'DESC');
        } elseif (request('sort') == 'name_asc') {
            $query->orderBy('name', 'ASC');
        } elseif (request('sort') == 'name_desc') {
            $query->orderBy('name', 'DESC');
        } else {
            $query->orderBy('id', 'ASC');
        }

        $products = $query->paginate(12);
        $count = $products->total(); // Sử dụng total() để lấy tổng số sản phẩm

        $wishlist = new Wishlist;

        return view('user.pages.product_grid_sub', [
            'danhmuc' => $danhmuc,
            'categories' => $categories,
            'products' => $products,
            'count' => $count,
            'wishlist' => $wishlist
        ]);
    }

    // Phương thức để xử lý thêm/xóa sản phẩm vào/khỏi danh sách yêu thích
    public function wishlist(Request $request)
    {
        // Thêm/xóa sản phẩm vào/khỏi danh sách yêu thích và phản hồi với JSON
        if ($request->ajax()) {
            $data = $request->all();
            $wishlist = new Wishlist;
            $countWishlist = $wishlist->countWishlist($data['products_id']);
            if ($countWishlist == 0) {
                $wishlist->products_id = $data['products_id'];
                $wishlist->users_id = $data['users_id'];
                $wishlist->save();
                return response()->json(['action' => 'add', 'message' => 'Product Added Successfully to Wishlist']);
            } else {
                Wishlist::where(['users_id' => Auth::user()->id, 'products_id' => $data['products_id']])->delete();
                return response()->json(['action' => 'remove', 'message' => 'Product Remove Successfully to Wishlist']);
            }
        }
    }

    // Phương thức để lấy tổng số lượng mục yêu thích
    public function total_wishlist()
    {
        // Lấy tổng số lượng mục yêu thích và phản hồi với JSON
        $total_wishlist = Wishlist::where(['users_id' => Auth::user()->id])->count();
        echo json_encode($total_wishlist);
    }

    public function product_latest_all()
    {
        // Hiển thị trang sản phẩm mới nhất
        $categories = Categories::all();

        // Lọc và sắp xếp giống như các hàm trước
        $query = Products::where('active', 1);

        if (request('sort') == 'price_asc') {
            $query->orderBy('price', 'ASC');
        } elseif (request('sort') == 'price_desc') {
            $query->orderBy('price', 'DESC');
        } elseif (request('sort') == 'name_asc') {
            $query->orderBy('name', 'ASC');
        } elseif (request('sort') == 'name_desc') {
            $query->orderBy('name', 'DESC');
        } else {
            $query->orderBy('created_at', 'DESC'); // Mặc định sắp xếp theo ngày tạo mới nhất
        }

        $products = $query->paginate(12);
        $count = $products->total();

        return view('user.pages.product_latest_all', ['products' => $products, 'categories' => $categories, 'count' => $count]);
    }

    // Phương thức để hiển thị trang sản phẩm giảm giá
    public function product_sale_all()
    {
        // Hiển thị trang sản phẩm giảm giá
        $categories = Categories::all();

        // Lọc sản phẩm có giá sale (giả sử có trường 'sale_price' trong bảng Products) và sắp xếp
        $query = Products::where('active', 1)->whereNotNull('price_new'); // Chỉ lấy sản phẩm có giá sale

        if (request('sort') == 'price_asc') {
            $query->orderBy('price_new', 'ASC'); // Sắp xếp theo giá sale
        } elseif (request('sort') == 'price_desc') {
            $query->orderBy('price_new', 'DESC'); // Sắp xếp theo giá sale
        } elseif (request('sort') == 'name_asc') {
            $query->orderBy('name', 'ASC');
        } elseif (request('sort') == 'name_desc') {
            $query->orderBy('name', 'DESC');
        } else {
            $query->orderBy('id', 'ASC'); // Mặc định sắp xếp theo id tăng dần
        }

        $products = $query->paginate(12);
        $count = $products->total();

        return view('user.pages.product_sale_all', ['count' => $count, 'products' => $products, 'categories' => $categories]);
    }

    public function product_featured_all()
    {
        // Hiển thị trang sản phẩm nổi bật
        $categories = Categories::all();

        // Lọc và sắp xếp giống như các hàm trước
        $query = Products::where('active', 1)->where('featured_product', 1);

        if (request('sort') == 'price_asc') {
            $query->orderBy('price', 'ASC');
        } elseif (request('sort') == 'price_desc') {
            $query->orderBy('price', 'DESC');
        } elseif (request('sort') == 'name_asc') {
            $query->orderBy('name', 'ASC');
        } elseif (request('sort') == 'name_desc') {
            $query->orderBy('name', 'DESC');
        } else {
            $query->orderBy('id', 'ASC');
        }

        $products = $query->paginate(12);
        $count = $products->total();

        return view('user.pages.product_featured_all', ['products' => $products, 'categories' => $categories, 'count' => $count]);
    }

    // Phương thức để hiển thị trang tất cả sản phẩm
    public function product_all(Request $request)
    {
        $categories = Categories::all();
        $wishlist = new Wishlist;

        $query = Products::where('active', 1);

        // Handle sorting logic
        if ($request->has('sort')) {
            switch ($request->input('sort')) {
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                    // Add more sorting options as needed
            }
        }

        // Fetch the paginated results after applying sorting
        $search = $query->paginate(15);
        $count = $search->total();

        return view('user.pages.product_all', [
            'categories' => $categories,
            'search'     => $search,
            'count'      => $count,
            'wishlist'   => $wishlist,
        ]);
    }

    // Phương thức để xử lý tìm kiếm người dùng
    public function search_user(Request $request)
    {
        // Xử lý tìm kiếm người dùng và hiển thị trang tất cả sản phẩm tương ứng
        if ($request['search']) {
            $categories = Categories::all();
            $search = Products::where('active', 1)->where('name', 'LIKE', '%' . $request['search'] . '%')->latest()->Paginate(15);
            $count = count($search);
            return view('user.pages.product_all', ['categories' => $categories, 'search' => $search, 'count' => $count]);
        } else {
            return redirect()->back()->with('canhbao', 'Empty Search');
        }
    }

    // Phương thức để hiển thị trang sản phẩm theo thương hiệu
    public function product_brand($id)
    {
        // Hiển thị trang sản phẩm theo thương hiệu
        $danhmuc = Brands::find($id);
        $categories = Categories::all();
        $wishlist = new Wishlist;

        // Thêm điều kiện lọc và sắp xếp giống như các hàm khác
        $query = Products::where('active', 1)->where('brands_id', $id);

        if (request('sort') == 'price_asc') {
            $query->orderBy('price', 'ASC');
        } elseif (request('sort') == 'price_desc') {
            $query->orderBy('price', 'DESC');
        } elseif (request('sort') == 'name_asc') {
            $query->orderBy('name', 'ASC');
        } elseif (request('sort') == 'name_desc') {
            $query->orderBy('name', 'DESC');
        } else {
            $query->orderBy('id', 'ASC'); // Mặc định sắp xếp theo id tăng dần
        }

        $products = $query->paginate(15); // Phân trang kết quả
        $count = $products->total(); // Lấy tổng số sản phẩm

        return view('user.pages.product_brand', [
            'categories' => $categories,
            'danhmuc' => $danhmuc,
            'products' => $products,
            'count' => $count,
            'wishlist' => $wishlist // Truyền wishlist vào view
        ]);
    }

    // Phương thức để hiển thị trang yêu thích
    public function wishlist_pages()
    {
        // Hiển thị trang yêu thích
        $pro_wish = Wishlist::all();
        $user = User::find(Auth::user()->id);
        $categories = Categories::all();
        $products = Products::where('users_id', $user)->orderBy('id', 'ASC')->Paginate(15);
        $count = count($products);
        $wishlist = new Wishlist;
        return view('user.pages.wishlist', ['categories' => $categories, 'products' => $products, 'count' => $count, 'wishlist' => $wishlist, 'pro_wish' => $pro_wish]);
    }

    // Phương thức để xử lý thêm đánh giá sản phẩm
    public function addRating(Request $request)
    {
        // Thêm đánh giá sản phẩm và chuyển hướng trở lại với thông báo thích hợp

        $data = $request->all();
        if (!isset($data['ratings'])) {
            return redirect()->back()->with('canhbao', 'Add at least one star rating for this Product');
        }
        $ratingCount = Rating::where(['users_id' => Auth::user()->id, 'products_id' => $data['products_id']])->count();
        if ($ratingCount > 0) {
            return redirect()->back()->with('canhbao', 'Your Rating is already exists for this Product');
        } else {
            $rating = new Rating;
            $rating->users_id = Auth::user()->id;
            $rating->products_id = $data['products_id'];
            $rating->ratings = $data['ratings'];
            $rating->content = $data['content'];
            $rating->save();
            return redirect()->back()->with('thongbao', 'Successfully');
        }
    }
    // khách (ko đăng nhập cũng có thể mua được)
    // Phương thức để hiển thị trang giỏ hàng
    // public function Getcart()
    // {
    //     // Hiển thị trang giỏ hàng
    //     // if (session()->has('cart')) {
    //     //     Cart::restore(session('cart'));
    //     // }

    //     return view('user.pages.product_cart');


    // }

    public function Getcart()
    {
        // Kiểm tra nếu session có thông tin giỏ hàng
        if (Auth::check()) {
            if (Cookie::has('cart')) {
                // Lấy nội dung giỏ hàng từ cookie và giải mã JSON thành mảng
                $cartContent = json_decode(Cookie::get('cart'), true);
                // $userId = Auth::id();
                // print_r($userId);
                // echo "Dữ liệu trong \$cartContent: <pre>";
                // print_r($cartContent);
                // echo "</pre>";
                // Kiểm tra nếu có dữ liệu trong giỏ hàng
                if (!empty($cartContent)) {
                    Cart::destroy(); // Xóa giỏ hàng hiện tại
                    // Cookie::forget('cart'); // Xóa cookie có tên là 'cart'
                    foreach ($cartContent as $item) {
                        // if($userId == $item['current_id']){
                        Cart::add($item['id'], $item['name'], $item['qty'], $item['price'], $item['weight'], [
                            'image' => $item['options']['image'],
                            'price_new' => $item['options']['price_new'],
                            'size' => $item['options']['size']
                        ]);
                        // }
                    }
                }
            }
        }

        Cart::setGlobalTax(0);

        return view('user.pages.product_cart');
    }

    public function Postcart(Request $request)
    {
        // Hiển thị trang giỏ hàng
        // echo ($request);
        // Cart::destroy();

        $products_id = $request->productid_hidden;
        $quantity = $request->qty;
        $products = Products::where('id', $products_id)->first();
        // Cart::add('293ad', 'Product 1', 1, 9.99, 550);
        if ($quantity >= $products['quantity']) {
            return redirect()->back()->with('canhbao', 'Vui lòng đặt hàng ít hơn số lượng: ' . $products['quantity'] . ' !!!');
        } else {

            // $userId = Auth::id();

            $data['id'] = $products_id;
            $data['qty'] = $quantity;
            $data['name'] = $products['name'];
            $data['price'] = $products['price'];
            $data['weight'] = 550;
            $data['options']['image'] = $products['image'];
            $data['options']['price_new'] = $products['price_new'];
            $data['options']['size'] = $products['size'];
            // $data['current_id'] = $userId;
            Cart::add($data);
            // Cart::destroy();
            Cart::setGlobalTax(0);
            if (Auth::check()) {
                $cartContent = Cart::content();
                Cookie::queue('cart', $cartContent, 43200);
            }
            // $cartContent = json_decode(Cookie::get('cart'), true);
            // echo "Dữ liệu trong \$cartContent: <pre>";
            // print_r($cartContent);
            // echo "</pre>";
        }

        return redirect('/cart')->with('thongbao', 'Sucessfully');
    }

    // Phương thức để lấy nội dung giỏ hàng
    public function index()
    {
        // Phương thức để lấy nội dung giỏ hàng
        return Cart::content();
    }

    // Phương thức để hiển thị trang thanh toán
    public function checkout()
    {
        // Hiển thị trang thanh toán
        if (Auth::check()) {
            $user = Auth::user();
            return view('user.pages.product_checkout', ['user' => $user]);
        } else {
            return view('user.pages.product_checkout', ['user' => 2]);
        }
    }
    // Phương thức để xóa mục khỏi giỏ hàng
    public function delete_cart($rowId)
    {
        // Xóa mục khỏi giỏ hàng
        Cart::update($rowId, 0);
        Orders_Detail::updated($rowId, 0);
        $cartContent = Cart::content();
        // Cookie::queue('cart', $cartContent, 43200); // Lưu giỏ hàng vào cookie
        Cookie::queue('cart', $cartContent, 43200); // Lưu giỏ hàng vào cookie
        return redirect('/cart')->with('thongbao', 'Sucessfully');
    }

    // Phương thức để cập nhật giỏ hàng // chưa đăng nhập
    public function update_cart(Request $request)
    {
        // Cập nhật giỏ hàng và chuyển hướng với thông báo thích hợp
        $rowId = $request->rowId_cart;
        $quantity = $request->cart_quantity;
        Cart::update($rowId, $quantity);
        $cartContent = Cart::content();
        // Cookie::queue('cart', $cartContent, 43200); // Lưu giỏ hàng vào cookie
        Cookie::queue('cart', $cartContent, 43200); // Lưu giỏ hàng vào cookie
        return redirect('/cart')->with('thongbao', 'Sucessfully');
    }
    public function clearCartManually()
    {
        $response = new Response(); // Tạo một đối tượng response mới

        // Đặt cookie mới với thời gian sống 0 để xóa cookie hiện tại
        $response->withCookie(cookie()->forget('cart'));

        return $response; // Trả về response
    }

    public function order_place(Request $request)
    {
        if (Auth::check()) {

            $content = Cart::content();

            $orders = array();
            $orders['users_id'] = Auth::user()->id;
            $orders['lastname'] = $request->lastname;
            $orders['firstname'] = $request->firstname;
            $orders['address'] = $request->address;
            $orders['district'] = $request->district;
            $orders['city'] = $request->city;
            $orders['phone'] = $request->phone;
            $orders['email'] = $request->email;
            $orders['content'] = $request->content;
            $orders['total'] = (int)preg_replace("/[,]+/", "", Cart::total(0));
            $orders['created_at'] =  now();
            // dd((int)preg_replace("/[,]+/", "", Cart::total(0)));
            $orders_id = Orders::insertGetId($orders);

            //insert order_details
            foreach ($content as $value) {
                $orders_detail['orders_id'] = $orders_id;
                $orders_detail['product_id'] = $value->id;
                $orders_detail['name'] = $value->name;
                $orders_detail['image'] = $value->options->image;
                $orders_detail['quantity'] = $value->qty;
                $orders_detail['price'] = $value->price;
                Orders_Detail::create($orders_detail);
                // Giảm số lượng sản phẩm trong bảng 'product'
                $product = Products::find($value->id);
                $product->quantity -= $value->qty;
                $product->save();
            }
            Cart::destroy();
            Cookie::queue(Cookie::forget('cart'));
            // session()->forget('cart');
            cookie()->forget('cart');
            // return redirect()->route('your_orders_detail', $orders_id)->with('thongbao', 'Đặt hàng thành công');
            return redirect('/give_mail_your_order/' . $orders_id)->with('thongbao', 'Successfully' . $orders_id);
        } else {
            $content = Cart::content();
            //echo $content;
            //insert orders
            $orders = array();
            $orders['users_id'] = 2;
            $orders['lastname'] = $request->lastname;
            $orders['firstname'] = $request->firstname;
            $orders['address'] = $request->address;
            $orders['district'] = $request->district;
            $orders['city'] = $request->city;
            $orders['phone'] = $request->phone;
            $orders['email'] = $request->email;
            $orders['content'] = $request->content;
            $orders['total'] = (int)preg_replace("/[,]+/", "", Cart::total(0));
            $orders['created_at'] =  now();
            // dd((int)preg_replace("/[,]+/", "", Cart::total(0)));
            $orders_id = Orders::insertGetId($orders);

            //insert order_details
            foreach ($content as $value) {
                $orders_detail['orders_id'] = $orders_id;
                $orders_detail['product_id'] = $value->id;
                $orders_detail['name'] = $value->name;
                $orders_detail['image'] = $value->options->image;
                $orders_detail['quantity'] = $value->qty;
                $orders_detail['price'] = $value->price;
                Orders_Detail::create($orders_detail);
                // Giảm số lượng sản phẩm trong bảng 'product'
                $product = Products::find($value->id);
                $product->quantity -= $value->qty;
                $product->save();
            }
            Cart::destroy();
            Cookie::queue(Cookie::forget('cart'));
            // cookie()->forget('cart');
            // return redirect()->route('your_orders_detail', $orders_id)->with('thongbao', 'Đặt hàng thành công');
            return redirect('/give_mail_your_order/' . $orders_id)->with('thongbao', 'Successfully' . $orders_id);
        }
    }
    //-------------------------------------------------------------------------------------------------------//
    // Phương thức để chỉnh sửa hình ảnh người dùng
    public function edit_img(Request $request)
    {
        // Chỉnh sửa hình ảnh người dùng và chuyển hướng với thông báo thích hợp
        $user = User::find(Auth::user()->id);
        if ($request->hasFile('Image')) {
            $file =  $request->file('Image');
            $format = $file->getClientOriginalExtension();
            if ($format != 'jpg' && $format != 'jpeg' && $format != 'png') {
                return redirect('/profile')->with('thongbao', 'Không hỗ trợ ' . $format);
            }
            $name = $file->getClientOriginalName();
            $img = Str::random(4) . '-' . $name;
            while (file_exists("upload/avatar" . $img)) {
                $img = Str::random(4) . '-' . $name;
            }
            $file->move('upload/avatar/', $img);
            if ($user['image'] != '') {
                if ($user['image'] != 'avatar.jpg') {
                    unlink('upload/avatar/' . $user->image);
                }
            }
            User::where('id', Auth::user()->id)->update(['image' => $img]);
            // $request['image'] = $img;
        }
        return redirect('profile')->with('thongbao', 'Update successfully!');
    }

    // Phương thức để chỉnh sửa hồ sơ người dùng
    public function edit_profile(Request $request)
    {
        // Chỉnh sửa hồ sơ người dùng và chuyển hướng với thông báo thích hợp
        $user = User::find(Auth::user()->id);
        if ($request['changepasswordprofile'] == 'on') {
            $request->validate([
                'password' => 'required',
                'passwordagain' => 'required|same:password'
            ], [
                'password.required' => 'Vui lòng nhập mật khẩu mới',
                'passwordagain.required' => 'Vui lòng nhập lại mật khẩu mới',
                'passwordagain.same' => 'Mật khẩu nhập lại không đúng'
            ]);
            $request['password'] = bcrypt($request['password']);
        }
        $user->update($request->all());
        // User::where('id',Auth::user()->id)->update($request->all());
        return redirect('/profile')->with('thongbao', 'Cập nhật thành công');
        // dd($user);
    }

    // Phương thức để hiển thị danh sách đơn hàng (chỉ có quản trị viên mới được truy cập)    
    public function orders_list()
    {
        // Hiển thị danh sách đơn hàng
        $orders = Orders::all();
        return view('admin.orders.list', ['orders' => $orders]);
    }
    public function orders_details($orders_id)
    {
        $orders_detail = Orders_Detail::where('orders_id', $orders_id)->get();
        return view('admin.orders.details', ['orders_detail' => $orders_detail]);
    }
    public function update(Request $request, $id)
    {
        Orders::find($id)->update($request->all());
        return redirect()->back()->with('thongbao', "Successfully");
    }
    public function your_orders(Request $request)
    {
        if (Auth::check()) {
            $query = Orders::where('users_id', Auth::id());

            // Handle sorting logic (only if sort is not empty)
            if ($request->filled('sort')) {
                $sort = $request->input('sort');
                $query->where('status', $sort);
            }

            // Handle search logic
            if ($request->has('query')) {
                $searchQuery = $request->input('query');
                $query->where(function ($subQuery) use ($searchQuery) {
                    $subQuery->where('id', 'LIKE', "%$searchQuery%")
                        ->orWhere('lastname', 'LIKE', "%$searchQuery%")
                        ->orWhere('firstname', 'LIKE', "%$searchQuery%")
                        ->orWhere('phone', 'LIKE', "%$searchQuery%")
                        ->orWhere('address', 'LIKE', "%$searchQuery%")
                        ->orWhere('district', 'LIKE', "%$searchQuery%")
                        ->orWhere('city', 'LIKE', "%$searchQuery%")
                        ->orWhere('content', 'LIKE', "%$searchQuery%");
                });
            }

            $orders = $query->get();

            return view('user.pages.orders', ['orders' => $orders]);
        } else {
            return redirect()->route('user.home');
        }
    }

    public function delete_orders($id)
    {
        // Tìm đơn hàng
        $order = Orders::find($id);

        if ($order) {
            // Lấy các chi tiết đơn hàng liên quan
            $orderDetails = Orders_Detail::where('orders_id', $id)->get();

            foreach ($orderDetails as $orderDetail) {
                // Tìm sản phẩm liên quan
                $product = Products::find($orderDetail->product_id);

                if ($product) {
                    // Tăng số lượng sản phẩm trong bảng product
                    $product->quantity += $orderDetail->quantity;
                    $product->save();
                }
            }
            // Xóa đơn hàng
            $order->delete($id);

            return response()->json(['success' => 'Delete Successfully']);
        } else {
            return response()->json(['error' => 'Order not found'], 404);
        }
    }

    public function your_orders_detail($id)
    {
        $order = Orders::findOrFail($id); // Lấy thông tin đơn hàng từ ID
        $orders_detail = Orders_Detail::where('orders_id', $id)->get();

        // Kiểm tra xem đơn hàng có thuộc về người dùng hiện tại không
        if (Auth::check() && $order->users_id !== Auth::id()) {
            abort(403, 'Unauthorized'); // Hoặc chuyển hướng đến trang khác
        }

        return view('user.pages.orders_detail', [
            'order' => $order, // Truyền thông tin đơn hàng vào view
            'orders_detail' => $orders_detail
        ]);
    }

    public function discount(Request $request)
    {
        $discounts = Discounts::all();
        foreach ($discounts as $value) {
            if ($value['code'] == $request->code) {
                if ($value['active'] == 1) {
                    $data = $value['discounts'];
                    Cart::setGlobalDiscount($data);
                    return redirect()->back()->with('thongbao', 'Apply Coupon Successfully');
                } else {
                    return redirect()->back()->with('canhbao', 'Code not available');
                }
            }
        }
        return redirect()->back()->with('canhbao', 'Wrong coupon code');
    }
    public function delete_discount()
    {
        Cart::setGlobalDiscount(0);
        return redirect()->back()->with('thongbao', 'Delete Coupon Successfully');
    }

    public function execPostRequest($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            )
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        //execute post
        $result = curl_exec($ch);
        //close connection
        curl_close($ch);
        return $result;
    }

    public function momo_payment(Request $request)
    {
        $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
        $partnerCode = 'MOMOBKUN20180529';
        $accessKey = 'klm05TvNBzhg7h7j';
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $orderInfo = "Thanh toán qua MoMo";

        $cartContent = Cart::content();
        $amount = Cart::total(0, '', '');
        $orderId = uniqid(); // Lấy ID của đơn hàng mới nhất

        $redirectUrl = route('momo.check'); // Success URL
        $ipnUrl = route('momo.check'); // Success URL
        $requestId = uniqid();
        $requestType = "payWithATM";
        $extraData = "";

        $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;
        $signature = hash_hmac("sha256", $rawHash, $secretKey);
        $data = array(
            'partnerCode' => $partnerCode,
            'partnerName' => "Test",
            "storeId" => "MomoTestStore",
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature
        );

        $cartContent = Cart::content()->toJson(); // Chuyển nội dung giỏ hàng thành JSON
        Cookie::queue('cart_backup', $cartContent, 43200);

        $result = $this->execPostRequest($endpoint, json_encode($data));
        $jsonResult = json_decode($result, true);  // decode json

        $this->order_place($request);
        return redirect($jsonResult['payUrl']);
    }

    public function momo_check(Request $request)
    {
        $resultCode = $request->get('resultCode');
        $latestOrder = Orders::orderBy('created_at', 'desc')->first(); // Tìm đơn hàng mới nhất
        $orderId = $latestOrder->id; // Lấy ID của đơn hàng mới nhất

        if ($resultCode === '0') { // Success
            $this->handleMomoSuccess($orderId);
            return redirect('/give_mail_your_order/' . $orderId)->with('thongbao', 'Successfully' . $orderId);
        } else { // Failure or cancellation
            $this->handleMomoFailure($orderId);
            return redirect()->route('cart')->with('canhbao', 'Thanh toán không thành công. Vui lòng thử lại.');
        }
    }

    private function handleMomoSuccess($orderId)
    {
        // Tìm đơn hàng dựa trên ID
        $order = Orders::find($orderId);

        if ($order) {
            $order->payment_status = 2; // Set payment_status to 2 (paid)
            $order->save();

            // Xóa giỏ hàng và cookie
            Cart::destroy();
            Cookie::queue(Cookie::forget('cart'));
            session()->forget('cart');
        } else {
            // Xử lý khi không tìm thấy đơn hàng (nếu cần)
        }
    }

    // Hàm xử lý khi thanh toán thất bại hoặc bị hủy
    private function handleMomoFailure($orderId)
    {
        // Tìm đơn hàng dựa trên ID
        $order = Orders::find($orderId);

        if ($order) {
            $orderDetails = Orders_Detail::where('orders_id', $orderId)->get();
            foreach ($orderDetails as $orderDetail) {
                $product = Products::find($orderDetail->product_id);
                if ($product) {
                    $product->quantity += $orderDetail->quantity;
                    $product->save();
                }
            }

            // Xóa đơn hàng
            $order->delete();
        } else {
            if (Cookie::has('cart_backup')) {
                $cartContent = json_decode(Cookie::get('cart_backup'), true);
                Cart::destroy(); // Xóa giỏ hàng hiện tại
                foreach ($cartContent as $item) {
                    Cart::add($item['id'], $item['name'], $item['qty'], $item['price'], 0, $item['options']);
                }
                Cookie::queue(Cookie::forget('cart_backup')); // Xóa cookie sau khi đã khôi phục
            }
        }
    }


    public function vnpay_payment(Request $request)
    {
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = route('vnpay.check'); // Success URL
        $vnp_TmnCode = "0YKCXJ3P"; //Mã website tại VNPAY 
        $vnp_HashSecret = "2YV8L7KX9HRPTBF2YNXMSAEIBZE6BO2R"; //Chuỗi bí mật

        // Lấy thông tin đơn hàng từ giỏ hàng (Cart)
        $cartContent = Cart::content();

        $vnp_TxnRef = uniqid(); // Lấy ID của đơn hàng mới nhất
        $vnp_OrderInfo = "Thanh toán đơn hàng #" . $vnp_TxnRef;
        $vnp_OrderType = "billpayment";

        $vnp_Amount = Cart::total(0, '', '') * 100; // Tổng giá trị đơn hàng (đơn vị: VNĐ)
        $vnp_Locale = 'vn';
        $vnp_BankCode = 'NCB';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        //Add Params of 2.0.1 Version
        // $vnp_ExpireDate = $_POST['txtexpire'];

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
            // "vnp_ExpireDate"=>$vnp_ExpireDate,
        );

        if (isset($vnp_BankCode) && $vnp_BankCode != "") {
            $inputData['vnp_BankCode'] = $vnp_BankCode;
        }
        if (isset($vnp_Bill_State) && $vnp_Bill_State != "") {
            $inputData['vnp_Bill_State'] = $vnp_Bill_State;
        }

        //var_dump($inputData);
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret); //  
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }
        $returnData = array(
            'code' => '00', 'message' => 'success', 'data' => $vnp_Url
        );

        $cartContent = Cart::content()->toJson(); // Chuyển nội dung giỏ hàng thành JSON
        Cookie::queue('cart_backup', $cartContent, 43200);

        if (isset($_POST['redirect'])) {
            $this->order_place($request);
            header('Location: ' . $vnp_Url);
            die();
        } else {
            echo json_encode($returnData); // Initial request, not a response from VNPay
        }
        // vui lòng tham khảo thêm tại code demo
    }

    public function vnpay_check(Request $request)
    {
        // Get response code and transaction reference
        $vnp_ResponseCode = $request->get('vnp_ResponseCode');
        $latestOrder = Orders::orderBy('created_at', 'desc')->first(); // Tìm đơn hàng mới nhất
        $orderId = $latestOrder->id;

        // Check response code and handle accordingly
        if ($vnp_ResponseCode === '00') { // Success
            $this->handleVnpaySuccess($orderId);
            return redirect('/give_mail_your_order/' . $orderId)->with('thongbao', 'Successfully' . $orderId);
        } else { // Failure or cancellation
            $this->handleVnpayFailure($orderId);
            return redirect()->route('cart')->with('canhbao', 'Thanh toán không thành công. Vui lòng thử lại.');
        }
    }

    // Hàm xử lý khi thanh toán thành công
    private function handleVnpaySuccess($orderId)
    {
        // Tìm đơn hàng dựa trên ID
        $order = Orders::find($orderId);

        if ($order) {
            $order->payment_status = 2; // Set payment_status to 2 (paid)
            $order->save();

            // Xóa giỏ hàng và cookie
            Cart::destroy();
            Cookie::queue(Cookie::forget('cart'));
            session()->forget('cart');
        } else {
            // Xử lý khi không tìm thấy đơn hàng (nếu cần)
        }
    }

    // Hàm xử lý khi thanh toán thất bại hoặc bị hủy
    private function handleVnpayFailure($orderId)
    {
        // Tìm đơn hàng dựa trên ID
        $order = Orders::find($orderId);

        if ($order) {
            $orderDetails = Orders_Detail::where('orders_id', $orderId)->get();
            foreach ($orderDetails as $orderDetail) {
                $product = Products::find($orderDetail->product_id);
                if ($product) {
                    $product->quantity += $orderDetail->quantity;
                    $product->save();
                }
            }

            // Xóa đơn hàng
            $order->delete();
        } else {
            if (Cookie::has('cart_backup')) {
                $cartContent = json_decode(Cookie::get('cart_backup'), true);
                Cart::destroy(); // Xóa giỏ hàng hiện tại
                foreach ($cartContent as $item) {
                    Cart::add($item['id'], $item['name'], $item['qty'], $item['price'], 0, $item['options']);
                }
                Cookie::queue(Cookie::forget('cart_backup')); // Xóa cookie sau khi đã khôi phục
            }
        }
    }

    public function send_passreset_token(Request $request)
    {
        $data = $request->all();
        // print_r($data);

        $now = Carbon::now('Asia/Ho_Chi_Minh')->format('d-m-Y');
        $title = '[TechZone] Lấy lại mật khẩu / Password retrieval';

        $user = User::where('email', '=', $data['gmail'])->get();
        print_r($data['gmail']);
        foreach ($user as $key => $value) {
            $user_id = $value->id;
        }
        if ($user) {
            $count = $user->count();
            print_r($count);
            if ($count == 0) {
                return redirect()->back()->with('error', 'Email does not exist.');
            } else {
                $token_random = Str::random(191);
                $user = User::find($user_id);
                $user->reset_token = $token_random;
                $user->save();

                // Send email
                $to_email = $data['gmail'];
                $link_reset_pass = url('/update-new-pass?email=' . $to_email . '&token=' . $token_random);
                $mail_data = [
                    "name" => $title,
                    "body" => $link_reset_pass,
                    'email' => $to_email
                ];

                Mail::send('user.forget_notify', ['data' => $mail_data], function ($message) use ($title, $mail_data) {
                    $message->to($mail_data['email'])->subject($title);
                    $message->from('no-reply@yourdomain.com', 'Your Application Name');
                });
            }
            return redirect()->back()->with('thongbao', 'Password reset link has been sent to your email.');
        }
    }

    public function solve_update_new_pass(Request $request)
    {
        $data = $request->all();
        $gmail = $data['gmail'];
        $token = $data['token'];
        $token_random = Str::random(191);

        $newPassword = $data['newpass'];
        $newPasswordConfirmation = $data['newpass_confirmation']; // Lấy giá trị xác nhận mật khẩu

        if ($newPassword !== $newPasswordConfirmation) {
            return redirect()->back()->with('', __('lang.password_not_match'));
        }

        $user = User::where('email', '=', $gmail)->where('reset_token', '=', $token)->get();
        $count = $user->count();
        if ($count == 1) {

            foreach ($user as $key => $value) {
                $user_id = $value->id;
            }
            $reset = User::find($user_id);
            $reset->password = bcrypt($newPassword);
            $reset->reset_token = $token_random;
            $reset->save();
            return redirect('/login')->with("success", 'Update successful !');
        } else {
            return redirect('/forgetpassword')->with("error", 'Request Timeout! gmail:' . $gmail . 'check' . $count . 'token:' . $token);
        }
    }

    public function give_mail_your_order($id)
    {

        if (Auth::check()) {
            $orders_id = $id;
            // session()->forget('orders_id_use');
            Cart::destroy();
            $content = Orders::find($orders_id);
            // if($content==null){
            //     $maxOrderId = Orders::max('id');
            //     $content = Orders::find($maxOrderId);
            // }
            $gmail = $content['email'];
            if ($gmail == null) {
                $gmail = 'hagiabao980@gmail.com';
            }
            $phone = $content['phone'];
            $title = 'Đơn hàng của khách hàng có số điện thoại ' . $phone;


            // Send email
            $to_email = $gmail;
            $link_order = url('/your_orders_detail/' . $id);
            $orders_detail = Orders_Detail::where('orders_id', $orders_id)->get(); // Lấy chi tiết đơn hàng

            $mail_data = [
                "name" => $title,
                "body" => $link_order,
                'email' => $to_email,
                'order' => $content,             // Truyền thông tin order
                'orders_detail' => $orders_detail // Truyền chi tiết đơn hàng
            ];

            Mail::send('user.order_mail', [
                'order' => $content,
                'orders_detail' => $orders_detail,
                'body' => $link_order
            ], function ($message) use ($title, $mail_data) {
                $message->to($mail_data['email'])->subject($title);
                $message->from('no-reply@yourdomain.com', 'Your Application Name');
            });

            return redirect('/your_orders_detail/' . $id)->with('thongbao', 'Successfully và đã gửi thông tin đến gmail');
        } else {

            $orders_id = $id;
            // session()->forget('orders_id_use');
            Cart::destroy();
            $content = Orders::find($orders_id);
            // if($content==null){
            //     $maxOrderId = Orders::max('id');
            //     $content = Orders::find($maxOrderId);
            // }
            $gmail = $content['email'];
            if ($gmail == null) {
                $gmail = 'hagiabao980@gmail.com';
            }
            $phone = $content['phone'];
            $title = 'Đơn hàng của khách hàng có số điện thoại ' . $phone;


            // Send email
            $to_email = $gmail;
            $link_order = url('/your_orders_detail/' . $id);
            $orders_detail = Orders_Detail::where('orders_id', $orders_id)->get(); // Lấy chi tiết đơn hàng

            $mail_data = [
                "name" => $title,
                "body" => $link_order,
                'email' => $to_email,
                'order' => $content,             // Truyền thông tin order
                'orders_detail' => $orders_detail // Truyền chi tiết đơn hàng
            ];

            Mail::send('user.order_mail', [
                'order' => $content,
                'orders_detail' => $orders_detail,
                'body' => $link_order
            ], function ($message) use ($title, $mail_data) {
                $message->to($mail_data['email'])->subject($title);
                $message->from('no-reply@yourdomain.com', 'Your Application Name');
            });

            return redirect('/your_orders_detail/' . $id)->with('thongbao', 'Successfully và đã gửi thông tin đến gmail' . $id);
        }
    }
}