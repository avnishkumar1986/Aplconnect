<?php

namespace Modules\SalesDistribution\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTarget extends Model
{
    protected $table = 'tbl_product_targets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'status' => 'boolean',
            'total_sale' => 'decimal:2',
            ...array_fill_keys(self::productColumns(), 'decimal:2'),
        ];
    }

    public static function productColumns(): array
    {
        return [
            'product1_pvc_pipes', 'product2_colum_pipes', 'product3_casing_pipe', 'product4_hdpe_pipe',
            'product5_astm_pipe', 'product6_cpvc_pipe', 'product7_ppr_pipe', 'product8_sprk_pipe',
            'product9_swr_pipe', 'product10_agri_swr_fit', 'product11_hdpe_fit', 'product12_astm_fit',
            'product13_sprk_fit', 'product14_cpvc_fit', 'product15_ppr_fit', 'product16_mdpe_fit',
            'product17_solvent', 'product18_bathfit', 'product19_watertank', 'product20_mdpe_pipe',
            'product21_plumbing_pipe', 'product22_opvc', 'product23_dwc_pipe', 'product24_other_products',
        ];
    }
}
