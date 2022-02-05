<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\Bitrix\BitrixServiceCompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Validator;
use Intervention\Image\Facades\Image;

class PartnerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $res['partner'] = Partner::all ()->sortBy ('name');
        return view ('partner.partners', $res);
    }

    public function crm_import (Request $request) {
        $bitrix_company = new BitrixServiceCompanyService();
        $result = $bitrix_company->ByType ('Partner');

        $res = [];
        foreach ($result as $item) {
            // TODO: do not store duplicates!
            $existing = Partner::where ('bitrix_id', $item['ID'])->get ();
            if (count ($existing) > 0)
                continue;
            $supplier = new Partner([
                'bitrix_id' => $item['ID'],
                'name' => $item['TITLE'],
            ]);
            $supplier->save ();
            Log::info ('Saved', ['id' => $supplier['id']]);
            $res[] = [
                'bitrix_id' => $item['ID'],
                'name' => $item['TITLE'],
            ];
        }

        return redirect ()->route ('partners.index');
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Partner  $partner
     * @return \Illuminate\Http\Response
     */
    public function show(Partner $partner)
    {
        return view ('partner.partner_show', ['partner' => $partner]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Partner  $partner
     */
    public function edit(Partner $partner)
    {

        return view ('partner.partner_edit', [
            'partner' => $partner,
            'suggested' => strtolower (str_replace (' ', '', $partner['name'])),
            'noshorthand' => ($partner['shorthand']) !== null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Partner  $partner
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Partner $partner)
    {
        $this->validate($request, [
            'image' => 'nullable',
            'image.*' => 'mimes:jpeg,jpg,gif,png'
        ]);
        $shorthand = $request['shorthand'];
        if (!preg_match("/[a-z0-9]+/", $shorthand)) {
            return redirect ()->route ('partners.edit', [
                'partner' => $partner,
                'shorthand' => strtolower (str_replace (' ', '', $partner['name'])),
                'noshorthand' => true,
            ]);
        }
        $partner['email'] = $request['email'];
        $partner['shorthand'] =$request['shorthand'];

        // getting image file
        $image = $request->File('image');
        if($image){
        // saving image filename
        $input['imagename'] = time().'.'.$image->extension();
        // giving path to the image
        $destinationPath = public_path('/img/partner-landingpage/thumbnail');
        // making thumbnail of the image in the desired path
        $img = Image::make($image->path());
        $img->resize(300, 300, function ($constraint) {
            $constraint->aspectRatio();
        })->save($destinationPath.'/'.$input['imagename']); //saving the image in the path

        // saving thumbnail image in db variable
        $partner['thumbnail'] = $input['imagename'];

        // saving full image in path
        $destinationPath = public_path('/img/partner-landingpage/');
        $image->move($destinationPath, $input['imagename']);
        // saving full image in db variable
        $partner['image'] = $input['imagename'];
        }

        $partner['description'] = $request['description'];
        $partner['link'] = $request['link'];
        $partner->save ();
        return redirect ()->route ('partners.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Partner  $partner
     * @return \Illuminate\Http\Response
     */
    public function destroy(Partner $partner)
    {
        //
    }
}
