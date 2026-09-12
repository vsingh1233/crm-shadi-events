@props(['disabled'=>false])
<input @disabled($disabled) {{ $attributes->merge(['class'=>'rounded-md shadow-sm border-[#D9CCBB] focus:border-[#6B2A38] focus:ring-[#6B2A38]']) }}>
