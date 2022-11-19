<?php

namespace App\Http\Controllers\Product;

use App\Models\ProductSell;
use App\Models\ProductSellCategory;
use App\Models\ProductSellCustomerGroup;
use App\Models\ProductSellImages;
use App\Models\ProductSellLocation;
use App\Models\ProductSellPriceLocation;
use App\Models\ProductSellQuantity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Validator;

class ProductSellController
{
    public function Index(Request $request)
    {

        $itemPerPage = $request->itemPerPage;

        $page = $request->page;

        $data = DB::table('productSells as ps')
            ->join('productSellLocations as psl', 'psl.productSellId', 'ps.id')
            ->join('location as loc', 'loc.Id', 'psl.locationId')
            ->leftjoin('ProductSuppliers as psup', 'ps.productSupplierId', 'psup.id')
            ->leftjoin('ProductBrands as pb', 'ps.ProductBrandId', 'pb.Id')
            ->join('users as u', 'ps.userId', 'u.id')
            ->select(
                'ps.id as id',
                'ps.fullName as fullName',
                'loc.locationName as locationName',
                DB::raw("IFNULL(psup.supplierName,'') as supplierName"),
                DB::raw("IFNULL(pb.brandName,'') as brandName"),
                DB::raw("TRIM(ps.price)+0 as price"),
                'ps.pricingStatus',
                DB::raw("TRIM(psl.inStock)+0 as stock"),
                DB::raw("CASE WHEN ps.status = 1 THEN 'Active' ELSE 'Non Active' END as status"),
                'u.name as createdBy',
                DB::raw("DATE_FORMAT(ps.created_at, '%d/%m/%Y') as createdAt")
            )
            ->where('ps.isDeleted', '=', 0);


        if ($request->orderby) {
            $data = $data->orderBy($request->column, $request->orderby);
        }

        $data = $data->orderBy('ps.id', 'desc');

        $offset = ($page - 1) * $itemPerPage;

        $count_data = $data->count();
        $count_result = $count_data - $offset;

        if ($count_result < 0) {
            $data = $data->offset(0)->limit($itemPerPage)->get();
        } else {
            $data = $data->offset($offset)->limit($itemPerPage)->get();
        }

        $totalPaging = $count_data / $itemPerPage;

        return response()->json([
            'totalPaging' => ceil($totalPaging),
            'data' => $data
        ], 200);
    }

    public function Detail(Request $request)
    {
        $ProdSell = DB::table('ProductSells as ps')
            ->leftjoin('ProductBrands as pb', 'ps.ProductBrandId', 'pb.Id')
            ->leftjoin('ProductSuppliers as psup', 'ps.ProductSupplierId', 'psup.Id')
            ->select(
                'ps.Id as id',
                'ps.fullName',
                DB::raw("IFNULL(ps.SimpleName,'') as simpleName"),
                DB::raw("IFNULL(ps.SKU,'') as sku"),
                'ps.productBrandId',
                'pb.brandName as brandName',
                'ps.productSupplierId',
                'psup.supplierName as supplierName',
                'ps.status',
                'ps.pricingStatus',
                DB::raw("TRIM(ps.costPrice)+0 as costPrice"),
                DB::raw("TRIM(ps.marketPrice)+0 as marketPrice"),
                DB::raw("TRIM(ps.price)+0 as price"),
                'ps.isShipped',
                DB::raw("TRIM(ps.weight)+0 as weight"),
                DB::raw("TRIM(ps.length)+0 as length"),
                DB::raw("TRIM(ps.width)+0 as width"),
                DB::raw("TRIM(ps.height)+0 as height"),
                DB::raw("TRIM(ps.weight)+0 as weight"),
                DB::raw("IFNULL(ps.introduction,'') as introduction"),
                DB::raw("IFNULL(ps.description,'') as description"),
            )
            ->where('ps.id', '=', $request->id)
            ->first();

        $location =  DB::table('ProductSellLocations as psl')
            ->join('location as l', 'l.Id', 'psl.LocationId')
            ->select('psl.Id', 'l.locationName', 'psl.inStock', 'psl.lowStock')
            ->where('psl.productSellId', '=', $request->id)
            ->first();

        $ProdSell->location = $location;

        if ($ProdSell->pricingStatus == "CustomerGroups") {

            $CustomerGroups = DB::table('ProductSellCustomerGroups as psc')
                ->join('ProductSells as ps', 'psc.ProductSellId', 'ps.Id')
                ->join('CustomerGroups as cg', 'psc.CustomerGroupId', 'cg.Id')
                ->select(
                    'psc.id as Id',
                    'cg.customerGroup',
                    DB::raw("TRIM(psc.Price)+0 as price")
                )
                ->where('psc.ProductSellId', '=', $request->id)
                ->get();

            $ProdSell->customerGroups = $CustomerGroups;
        } elseif ($ProdSell->pricingStatus == "PriceLocations") {
            $PriceLocations = DB::table('ProductSellPriceLocations as psp')
                ->join('ProductSells as ps', 'psp.ProductSellId', 'ps.Id')
                ->join('location as l', 'psp.LocationId', 'l.Id')
                ->select(
                    'psp.id as id',
                    'l.locationName',
                    DB::raw("TRIM(psp.Price)+0 as Price")
                )
                ->where('psp.ProductSellId', '=', $request->id)
                ->get();

            $ProdSell->priceLocations = $PriceLocations;
        } else if ($ProdSell->pricingStatus == "Quantities") {

            $Quantities = DB::table('ProductSellQuantities as psq')
                ->join('ProductSells as ps', 'psq.ProductSellId', 'ps.Id')
                ->select(
                    'psq.id as id',
                    'psq.fromQty',
                    'psq.toQty',
                    DB::raw("TRIM(psq.Price)+0 as Price")
                )
                ->where('psq.ProductSellId', '=', $request->id)
                ->get();

            $ProdSell->quantities = $Quantities;
        }

        $ProdSell->categories = DB::table('productSellCategories as psc')
            ->join('productSells as ps', 'psc.productSellId', 'ps.id')
            ->join('productCategories as pc', 'psc.productCategoryId', 'pc.id')
            ->select(
                'psc.id as id',
                'pc.categoryName'
            )
            ->where('psc.ProductSellId', '=', $request->id)
            ->get();

        $ProdSell->images = DB::table('productSellImages as psi')
            ->join('productSells as ps', 'psi.productSellId', 'ps.id')
            ->select(
                'psi.id as id',
                'psi.labelName',
                'psi.realImageName',
                'psi.imagePath'
            )
            ->where('psi.productSellId', '=', $request->id)
            ->get();

        return response()->json($ProdSell,200);
    }

    public function Create(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'fullName' => 'required|string|max:30',
            'simpleName' => 'nullable|string',
            'productBrandId' => 'nullable|integer',
            'productSupplierId' => 'nullable|integer',
            'sku' => 'nullable|string',
            'status' => 'required|bool',
            'expiredDate' => 'nullable|date',
            'pricingStatus' => 'required|string',

            'costPrice' => 'required|numeric',
            'marketPrice' => 'required|numeric',
            'price' => 'required|numeric',
            'isShipped' => 'required|bool',
            'weight' => 'nullable|numeric',
            'length' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'introduction' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validate->fails()) {
            $errors = $validate->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        $ResultCategories = null;

        if ($request->categories) {
            $ResultCategories = json_decode($request->categories, true);
        }

        $ResultLocations = json_decode($request->locations, true);

        $validateLocation = Validator::make(
            $ResultLocations,
            [
                '*.locationId' => 'required|integer',
                '*.inStock' => 'required|integer',
                '*.lowStock' => 'required|integer',
            ],
            [
                '*.locationId.integer' => 'Location Id Should be Integer!',
                '*.inStock.integer' => 'In Stock Should be Integer',
                '*.lowStock.integer' => 'Low Stock Should be Integer'
            ]
        );

        if ($validateLocation->fails()) {
            $errors = $validateLocation->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        foreach ($ResultLocations as $Res) {

            $CheckDataBranch = DB::table('productSells as ps')
                ->join('productSellLocations as psl', 'psl.productSellId', 'ps.id')
                ->join('location as loc', 'psl.locationId', 'loc.id')
                ->select('ps.fullName as fullName', 'loc.locationName')
                ->where('ps.fullName', '=', $request->fullName)
                ->where('psl.locationId', '=', $Res['locationId'])
                ->first();

            if ($CheckDataBranch) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Product ' . $CheckDataBranch->fullName . ' Already Exist on Location ' . $CheckDataBranch->locationName . '!'],
                ], 422);
            }
        }

        if ($request->hasfile('images')) {

            $data_item = [];

            $files[] = $request->file('images');

            foreach ($files as $file) {

                foreach ($file as $fil) {

                    $file_size = $fil->getSize();

                    $file_size = $file_size / 1024;

                    $oldname = $fil->getClientOriginalName();

                    if ($file_size >= 5000) {

                        array_push($data_item, 'Foto ' . $oldname . ' lebih dari 5mb! Harap upload gambar dengan ukuran lebih kecil!');
                    }
                }
            }

            if ($data_item) {

                return response()->json([
                    'message' => 'Foto yang dimasukkan tidak valid!',
                    'errors' => $data_item,
                ], 422);
            }
        }

        if ($request->pricingStatus == "CustomerGroups") {

            if ($request->customerGroups) {
                $ResultCustomerGroups = json_decode($request->customerGroups, true);

                $validateCustomer = Validator::make(
                    $ResultCustomerGroups,
                    [

                        '*.customerGroupId' => 'required|integer',
                        '*.price' => 'required|numeric',
                    ],
                    [
                        '*.customerGroupId.integer' => 'Customer Group Id Should be Integer!',
                        '*.price.numeric' => 'Price Should be Numeric!'
                    ]
                );

                if ($validateCustomer->fails()) {
                    $errors = $validateCustomer->errors()->all();

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $errors,
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Customer Group can not be empty!'],
                ], 422);
            }
        } else if ($request->pricingStatus == "PriceLocations") {

            if ($request->priceLocations) {
                $ResultPriceLocations = json_decode($request->priceLocations, true);

                $validatePriceLocations = Validator::make(
                    $ResultPriceLocations,
                    [

                        'priceLocations.*.locationId' => 'required|integer',
                        'priceLocations.*.price' => 'required|numeric',
                    ],
                    [
                        '*.locationId.integer' => 'Location Id Should be Integer!',
                        '*.price.numeric' => 'Price Should be Numeric!'
                    ]
                );

                if ($validatePriceLocations->fails()) {
                    $errors = $validatePriceLocations->errors()->all();

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $errors,
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Price Location can not be empty!'],
                ], 422);
            }
        } else if ($request->pricingStatus == "Quantities") {

            if ($request->quantities) {
                $ResultQuantities = json_decode($request->quantities, true);

                $validateQuantity = Validator::make(
                    $ResultQuantities,
                    [

                        'quantities.*.fromQty' => 'required|integer',
                        'quantities.*.toQty' => 'required|integer',
                        'quantities.*.price' => 'required|numeric',
                    ],
                    [
                        '*.fromQty.integer' => 'From Quantity Should be Integer!',
                        '*.toQty.integer' => 'To Quantity Should be Integer!',
                        '*.price.numeric' => 'Price Should be Numeric!'
                    ]
                );

                if ($validateQuantity->fails()) {
                    $errors = $validateQuantity->errors()->all();

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $errors,
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Quantity can not be empty!'],
                ], 422);
            }
        }

        //INSERT DATA

        $flag = false;
        $res_data = [];

        foreach ($ResultLocations as $value) {

            $product = ProductSell::create([
                'fullName' => $request->fullName,
                'simpleName' => $request->simpleName,
                'sku' => $request->sku,
                'productBrandId' => $request->productBrandId,
                'productSupplierId' => $request->productSupplierId,
                'status' => $request->status,
                'expiredDate' => $request->expiredDate,
                'pricingStatus' => $request->pricingStatus,
                'costPrice' => $request->costPrice,
                'marketPrice' => $request->marketPrice,
                'price' => $request->price,
                'isShipped' => $request->isShipped,
                'weight' => $request->weight,
                'length' => $request->length,
                'width' => $request->width,
                'height' => $request->height,
                'introduction' => $request->introduction,
                'description' => $request->description,
                'height' => $request->height,
                'userId' => $request->user()->id,
            ]);

            ProductSellLocation::create([
                'productSellId' => $product->id,
                'locationId' => $value['locationId'],
                'inStock' => $value['inStock'],
                'lowStock' => $value['lowStock'],
                'userId' => $request->user()->id,
            ]);

            if ($ResultCategories) {

                foreach ($ResultCategories as $valCat) {
                    ProductSellCategory::create([
                        'productSellId' => $product->id,
                        'productCategoryId' => $valCat,
                        'userId' => $request->user()->id,
                    ]);
                }
            }

            $count = 0;

            $ResImageDatas = json_decode($request->imageDatas, true);

            if ($flag == false) {

                if ($request->hasfile('images')) {
                        info('masuk awal');
                    foreach ($files as $file) {
                        info('masuk');

                        foreach ($file as $fil) {

                            $name = $fil->hashName();

                            $fil->move(public_path() . '/ProductSellImages/', $name);

                            $fileName = "/ProductSellImages/" . $name;

                            $file = new ProductSellImages();
                            $file->productSellId = $product->id;
                            $file->labelName = $ResImageDatas[$count];
                            $file->realImageName = $fil->getClientOriginalName();
                            $file->imagePath = $fileName;
                            $file->userId = $request->user()->id;
                            $file->save();

                            array_push($res_data, $file);

                            $count += 1;
                        }
                    }

                    $flag = true;
                }
            } else {

                foreach ($res_data as $res) {
                    ProductSellImages::create([
                        'productSellId' => $product->id,
                        'labelName' => $res['labelName'],
                        'realImageName' => $res['realImageName'],
                        'imagePath' => $res['imagePath'],
                        'userId' => $request->user()->id,
                    ]);
                }
            }

            if ($request->pricingStatus == "CustomerGroups") {

                foreach ($ResultCustomerGroups as $CustVal) {
                    ProductSellCustomerGroup::create([
                        'productSellId' => $product->id,
                        'customerGroupId' => $CustVal['customerGroupId'],
                        'price' => $CustVal['price'],
                        'userId' => $request->user()->id,
                    ]);
                }
            } else if ($request->PricingStatus == "PriceLocations") {

                $ResultPriceLocations = json_decode($request->priceLocations, true);

                foreach ($ResultPriceLocations as $PriceVal) {
                    ProductSellPriceLocation::create([
                        'productSellId' => $product->id,
                        'locationId' => $PriceVal['locationId'],
                        'rice' => $PriceVal['price'],
                        'userId' => $request->user()->id,
                    ]);
                }
            } else if ($request->pricingStatus == "Quantities") {

                foreach ($ResultQuantities as $QtyVal) {
                    ProductSellQuantity::create([
                        'productSellId' => $product->id,
                        'fromQty' => $QtyVal['fromQty'],
                        'toQty' => $QtyVal['toQty'],
                        'price' => $QtyVal['price'],
                        'userId' => $request->user()->id,
                    ]);
                }
            }
        }

        return response()->json(
            [
                'message' => 'Insert Data Successful!',
            ],
            200
        );
    }

    public function Update(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'fullName' => 'required|string|max:30',
            'simpleName' => 'nullable|string',
            'productBrandId' => 'nullable|integer',
            'productSupplierId' => 'nullable|integer',
            'sku' => 'nullable|string',
            'status' => 'required|bool',
            'expiredDate' => 'nullable|date',
            'pricingStatus' => 'required|string',

            'costPrice' => 'required|numeric',
            'marketPrice' => 'required|numeric',
            'price' => 'required|numeric',
            'isShipped' => 'required|bool',
            'weight' => 'nullable|numeric',
            'length' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'introduction' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validate->fails()) {
            $errors = $validate->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        $prodSell = ProductSell::find($request->id);

        if (!$prodSell) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['Data not found!'],
            ], 422);
        }

        $validateLocation = Validator::make(
            $request->locations,
            [
                '*.id' => 'nullable|integer',
                '*.locationId' => 'required|integer',
                '*.inStock' => 'required|integer',
                '*.lowStock' => 'required|integer',
                '*.status' => 'required|string',
            ],
            [
                '*.id.integer' => 'Id Should be Integer!',
                '*.locationId.integer' => 'Location Id Should be Integer!',
                '*.inStock.integer' => 'In Stock Should be Integer',
                '*.lowStock.integer' => 'Low Stock Should be Integer',
                '*.status.string' => 'Status Should be String'
            ]
        );

        if ($validateLocation->fails()) {
            $errors = $validateLocation->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }



        foreach ($request->locations as $Res) {

            if ($Res['status'] == "new") {
                $CheckDataBranch = DB::table('productSells as ps')
                    ->join('productSellLocations as psl', 'psl.productSellId', 'ps.id')
                    ->join('location as loc', 'psl.locationId', 'loc.id')
                    ->select('ps.fullName as fullName', 'loc.locationName')
                    ->where('ps.fullName', '=', $request->fullName)
                    ->where('psl.locationId', '=', $Res['locationId'])
                    ->first();

                if ($CheckDataBranch) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['Product ' . $CheckDataBranch->fullName . ' Already Exist on Location ' . $CheckDataBranch->locationName . '!'],
                    ], 422);
                }
            }
        }

        if ($request->hasfile('images')) {

            $data_item = [];

            $files[] = $request->file('images');

            foreach ($files as $file) {

                foreach ($file as $fil) {

                    $file_size = $fil->getSize();

                    $file_size = $file_size / 1024;

                    $oldname = $fil->getClientOriginalName();

                    if ($file_size >= 5000) {

                        array_push($data_item, 'Foto ' . $oldname . ' lebih dari 5mb! Harap upload gambar dengan ukuran lebih kecil!');
                    }
                }
            }

            if ($data_item) {

                return response()->json([
                    'message' => 'Foto yang dimasukkan tidak valid!',
                    'errors' => $data_item,
                ], 422);
            }
        }

        if ($request->pricingStatus == "CustomerGroups") {

            if ($request->customerGroups) {
                //$ResultCustomerGroups = json_decode($request->customerGroups, true);

                $validateCustomer = Validator::make(
                    $request->customerGroups,
                    [
                        '*.id' => 'nullable|integer',
                        '*.customerGroupId' => 'required|integer',
                        '*.price' => 'required|numeric',
                        '*.status' => 'required|string',
                    ],
                    [
                        '*.id.integer' => 'Id Should be Integer!',
                        '*.customerGroupId.integer' => 'Customer Group Id Should be Integer!',
                        '*.price.numeric' => 'Price Should be Numeric!',
                        '*.status.string' => 'Status Should be String!'
                    ]
                );

                if ($validateCustomer->fails()) {
                    $errors = $validateCustomer->errors()->all();

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $errors,
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Customer Group can not be empty!'],
                ], 422);
            }
        } else if ($request->pricingStatus == "PriceLocations") {

            if ($request->priceLocations) {
                //$ResultPriceLocations = json_decode($request->priceLocations, true);

                $validatePriceLocations = Validator::make(
                    $request->priceLocations,
                    [
                        '*.id' => 'nullable|integer',
                        '*.locationId' => 'required|integer',
                        '*.price' => 'required|numeric',
                        '*.status' => 'required|string',
                    ],
                    [
                        '*.id.integer' => 'Id Should be Integer!',
                        '*.locationId.integer' => 'Location Id Should be Integer!',
                        '*.price.numeric' => 'Price Should be Numeric!',
                        '*.status.string' => 'Status Should be String!'
                    ]
                );

                if ($validatePriceLocations->fails()) {
                    $errors = $validatePriceLocations->errors()->all();

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $errors,
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Price Location can not be empty!'],
                ], 422);
            }
        } else if ($request->pricingStatus == "Quantities") {

            if ($request->quantities) {
                // $ResultQuantities = json_decode($request->quantities, true);

                $validateQuantity = Validator::make(
                    $request->quantities,
                    [
                        '*.id' => 'nullable|integer',
                        '*.fromQty' => 'required|integer',
                        '*.toQty' => 'required|integer',
                        '*.price' => 'required|numeric',
                        '*.status' => 'required|string',
                    ],
                    [
                        '*.id.integer' => 'Id Should be Integer!',
                        '*.fromQty.integer' => 'From Quantity Should be Integer!',
                        '*.toQty.integer' => 'To Quantity Should be Integer!',
                        '*.price.numeric' => 'Price Should be Numeric!',
                        '*.status.string' => 'Status Should be String!'
                    ]
                );

                if ($validateQuantity->fails()) {
                    $errors = $validateQuantity->errors()->all();

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $errors,
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Quantity can not be empty!'],
                ], 422);
            }
        }

        //UPDATE DATA   

        foreach ($request->locations as $resLoc) {

            if ($resLoc['status'] == "new") {
                ProductSellLocation::create([
                    'productSellId' => $request->id,
                    'locationId' => $resLoc['locationId'],
                    'inStock' => $resLoc['inStock'],
                    'lowStock' => $resLoc['lowStock'],
                    'userId' => $request->user()->id,
                ]);
            } elseif ($resLoc['status'] == "delete") {
                ProductSellLocation::create([
                    'productSellId' => $request->id,
                    'locationId' => $resLoc['locationId'],
                    'inStock' => $resLoc['inStock'],
                    'lowStock' => $resLoc['lowStock'],
                    'userId' => $request->user()->id,
                ]);
            } elseif ($resLoc['status'] == "update") {
                ProductSellLocation::create([
                    'productSellId' => $request->id,
                    'locationId' => $resLoc['locationId'],
                    'inStock' => $resLoc['inStock'],
                    'lowStock' => $resLoc['lowStock'],
                    'userId' => $request->user()->id,
                ]);
            }
        }


        return response()->json(
            [
                'message' => 'Update Data Successful!',
            ],
            200
        );
    }

    public function Delete(Request $request)
    {
        //check product on DB
        foreach ($request->id as $va) {
            $res = ProductSell::find($va);

            if (!$res) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['There is any Data not found!'],
                ], 422);
            }
        }

        //process delete data
        foreach ($request->id as $va) {

            $ProdSell = ProductSell::find($va);

            $ProdSellLoc = ProductSellLocation::where('ProductSellId', '=', $ProdSell->id)->get();

            if ($ProdSellLoc) {

                ProductSellLocation::where('ProductSellId', '=', $ProdSell->id)
                    ->update(
                        [
                            'deletedBy' => $request->user()->id,
                            'isDeleted' => 1,
                            'deletedAt' => Carbon::now()
                        ]
                    );
            }

            $ProdSellCat = ProductSellCategory::where('ProductSellId', '=', $ProdSell->id)->get();

            if ($ProdSellCat) {

                ProductSellCategory::where('ProductSellId', '=', $ProdSell->id)
                    ->update(
                        [
                            'deletedBy' => $request->user()->id,
                            'isDeleted' => 1,
                            'deletedAt' => Carbon::now()
                        ]
                    );

                $ProdSellCat->DeletedBy = $request->user()->id;
                $ProdSellCat->isDeleted = true;
                $ProdSellCat->DeletedAt = Carbon::now();
            }

            $ProdSellImg = ProductSellImages::where('ProductSellId', '=', $ProdSell->id)->get();

            if ($ProdSellImg) {

                ProductSellImages::where('ProductSellId', '=', $ProdSell->id)
                    ->update(
                        [
                            'deletedBy' => $request->user()->id,
                            'isDeleted' => 1,
                            'deletedAt' => Carbon::now()
                        ]
                    );
            }

            $ProdCustGrp = ProductSellCustomerGroup::where('ProductSellId', '=', $ProdSell->id)->get();
            if ($ProdCustGrp) {

                ProductSellCustomerGroup::where('ProductSellId', '=', $ProdSell->id)
                    ->update(
                        [
                            'deletedBy' => $request->user()->id,
                            'isDeleted' => 1,
                            'deletedAt' => Carbon::now()
                        ]
                    );
            }

            $ProdSellPrcLoc = ProductSellPriceLocation::where('ProductSellId', '=', $ProdSell->id)->get();

            if ($ProdSellPrcLoc) {

                ProductSellPriceLocation::where('ProductSellId', '=', $ProdSell->id)
                    ->update(
                        [
                            'deletedBy' => $request->user()->id,
                            'isDeleted' => 1,
                            'deletedAt' => Carbon::now()
                        ]
                    );
            }

            $ProdSellQty = ProductSellQuantity::where('ProductSellId', '=', $ProdSell->id)->get();

            if ($ProdSellQty) {

                ProductSellQuantity::where('ProductSellId', '=', $ProdSell->id)
                    ->update(
                        [
                            'deletedBy' => $request->user()->id,
                            'isDeleted' => 1,
                            'deletedAt' => Carbon::now()
                        ]
                    );
            }

            $ProdSell->DeletedBy = $request->user()->id;
            $ProdSell->isDeleted = true;
            $ProdSell->DeletedAt = Carbon::now();
        }

        return response()->json([
            'message' => 'Delete Data Successful',
        ], 200);
    }
}
