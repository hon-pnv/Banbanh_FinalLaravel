<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;

use App\Models\Slide;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\BillDetail;
// use PhpParser\Node\Expr\Print_;
use Illuminate\Support\Facades\Session;
use App\Models\Cart;
use App\Models\Comment;
use App\Models\Wishlist;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

// use Illuminate\Support\Facades\DB;


class PageController extends Controller
{
    public function getIndex()
    {
        $slide = Slide::all();
        $new_product  = Product::where('new', 1)->paginate(4);
        $promotion_product = Product::where('promotion_price', '<>', 0)->paginate(8);
        return view('page.trangchu', compact('slide', 'new_product', 'promotion_product'));
    }
    public function getLoaiSp($type)
    {
        $sp_theoloai = Product::where('id_type', $type)->get();
        $type_product = ProductType::all();
        $sp_khac = Product::where('id_type', '<>', $type)->paginate(3);

        return view('page.loai_sanpham', compact('sp_theoloai', 'type_product', 'sp_khac'));
    }

    public function getDetail(Request $request)
    {
        $sanpham = Product::where('id', $request->id)->first();
        $splienquan = Product::where('id', '<>', $sanpham->id, 'and', 'id_type', '=', $sanpham->id_type,)->paginate(3);
        $comments = Comment::where('id_product', $request->id)->get();
        return view('page.chitiet_sanpham', compact('sanpham', 'splienquan', 'comments'));
    }
    public function getContact()
    {
        return view('page.lienhe');
    }
    public function getAbout()
    {
        return view('page.about');
    }

    public function getIndexAdmin()
    {
        $products =  Product::all();
        return view('pageadmin.admin')->with(['products' => $products, 'sumSold' => count(BillDetail::all())]);
    }
    public function getAdminAdd()
    {
        return view('pageadmin.formAdd');
    }
    public function postAdminAdd(Request $request)
    {
        $product = new Product();
        if ($request->hasFile('inputImage')) {
            $file = $request->file('inputImage');
            $fileName = $file->getClientOriginalName('inputImage');
            $file->move('source/image/product', $fileName);
        }
        $file_name = null;
        if ($request->file('inputImage') != null) {
            $file_name = $request->file('inputImage')->getClientOriginalName();
        }

        $product->name = $request->inputName;
        $product->image = $file_name;
        $product->description = $request->inputDescription;
        $product->unit_price = $request->inputPrice;
        $product->promotion_price = $request->inputPromotionPrice;
        $product->unit = $request->inputUnit;
        $product->new = $request->inputNew;
        $product->id_type = $request->inputType;
        $product->save();
        return $this->getIndexAdmin();
    }

    public function postAdminDelete($id)
    {
        $product =  Product::find($id);
        $product->delete();
        return $this->getIndexAdmin();
    }
    public function getAdminEdit($id)
    {
        $product =  Product::find($id);
        return view('pageadmin.formEdit')->with('product', $product);
    }
    public function postAdminEdit(Request $request)
    {
        $id = $request->editId;

        $product = Product::find($id);
        if ($request->hasFile('editImage')) {
            $file = $request->file('editImage');
            $fileName = $file->getClientOriginalName('editImage');
            $file->move('source/image/product', $fileName);
        }

        if ($request->file('editImage') != null) {
            $product->image = $fileName;
        }

        $product->name = $request->editName;
        $product->description = $request->editDescription;
        $product->unit_price = $request->editPrice;
        $product->promotion_price = $request->editPromotionPrice;
        $product->unit = $request->editUnit;
        $product->new = $request->editNew;
        $product->id_type = $request->editType;
        $product->save();
        return $this->getIndexAdmin();
    }

    // --------------- CART -----------
    public function getAddToCart(Request $req, $id)
    {
        if (Session::has('user')) {
            if (Product::find($id)) {
                $product = Product::find($id);
                $oldCart = Session('cart') ? Session::get('cart') : null;
                $cart = new Cart($oldCart);
                $cart->add($product, $id);
                $req->session()->put('cart', $cart);
                return redirect()->back();
            } else {
                return '<script>alert("Kh??ng t??m th???y s???n ph???m n??y.");window.location.assign("/");</script>';
            }
        } else {
            return '<script>alert("Vui l??ng ????ng nh???p ????? s??? d???ng ch???c n??ng n??y.");window.location.assign("/login");</script>';
        }
    }
    public function getDelItemCart($id)
    {
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);
        $cart->removeItem($id);
        if (count($cart->items) > 0 && Session::has('cart')) {
            Session::put('cart', $cart);
        } else {
            Session::forget('cart');
        }
        return redirect()->back();
    }
    // ------------------------ CHECKOUT -------------------
    public function getCheckout()
    {
        if (Session::has('cart')) {
            $oldCart = Session::get('cart');
            $cart = new Cart($oldCart);
            return view('page.checkout')->with(['cart' => Session::get('cart'), 'product_cart' => $cart->items, 'totalPrice' => $cart->totalPrice, 'totalQty' => $cart->totalQty]);;
        } else {
            return redirect('trangchu');
        }
    }

    public function postCheckout(Request $req)
    {
        $cart = Session::get('cart');
        $customer = new Customer;
        $customer->name = $req->full_name;
        $customer->gender = $req->gender;
        $customer->email = $req->email;
        $customer->address = $req->address;
        $customer->phone_number = $req->phone;

        if (isset($req->notes)) {
            $customer->note = $req->notes;
        } else {
            $customer->note = "Kh??ng c?? ghi ch?? g??";
        }

        $customer->save();

        $bill = new Bill;
        $bill->id_customer = $customer->id;
        $bill->date_order = date('Y-m-d');
        $bill->total = $cart->totalPrice;
        $bill->payment = $req->payment_method;
        if (isset($req->notes)) {
            $bill->note = $req->notes;
        } else {
            $bill->note = "Kh??ng c?? ghi ch?? g??";
        }
        $bill->save();

        foreach ($cart->items as $key => $value) {
            $bill_detail = new BillDetail;
            $bill_detail->id_bill = $bill->id;
            $bill_detail->id_product = $key; //$value['item']['id'];
            $bill_detail->quantity = $value['qty'];
            $bill_detail->unit_price = $value['price'] / $value['qty'];
            $bill_detail->save();
        }

        Session::forget('cart');
        $wishlists = Wishlist::where('id_user', Session::get('user')->id)->get();
        if (isset($wishlists)) {
            foreach ($wishlists as $element) {
                $element->delete();
            }
        }
        // ----------- SEND EMAIL -----------
        $message = [
            'type' => 'Thanh to??n ????n h??ng ',
            'thanks' => 'C???m ??n ' . $req->name . ' ???? ?????t h??ng.',
            'cart' => $cart,
            'content' => '????n h??ng s??? t???i tay b???n s???m nh???t c?? th???.',
        ];
        SendEmail::dispatch($message, $req->email)->delay(now()->addMinute(1));

        // ----------- PAYMENT WITH VNPAY -----------
        if ($req->payment_method == "vnpay") {

            $vnp_TmnCode = "81QHJZL5"; //M?? website t???i VNPAY
            $vnp_HashSecret = "CFPJPTQFVUJBPVKTKGGRCVQUQQPOLGZD"; //Chu???i b?? m???t
            $vnp_Url = "http://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
            $vnp_Returnurl = "http://localhost:8000/return-vnpay";
            $vnp_TxnRef = rand(1, 100000000); //M?? ????n h??ng. Trong th???c t??? Merchant c???n insert ????n h??ng v??o DB v?? g???i m?? n??y sang VNPAY
            $vnp_OrderInfo = 'Thanh to??n ????n h??ng c???a b???n ';
            $vnp_OrderType = 'VNPay';
            $vnp_Amount = $_POST['tongtien'] * 100;
            $vnp_Locale = 'vn';
            $vnp_BankCode = 'NCB';
            $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
           

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
            if (isset($_POST['redirect'])) {
                header('Location: ' . $vnp_Url);
                die();
            } else {
                echo json_encode($returnData);
            }
        }
    }
    

    public function exportAdminProduct()
    {
        // -------------- EXPORT PDF-------------
        // share data to view
        $products =  Product::all();
        $pdf = PDF::loadView('PDF.exportProduct', compact('products'))->setPaper('a4', 'portrait');
        return $pdf->download('products.pdf');
    }
}
