import React, { useContext, useEffect } from 'react';
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';
import { GeneralContext } from '../contexts/GeneralContext';

const toMediaUrl = (path) => {
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) return path;
  return new URL(path, window.location.origin).toString();
};

const AdsBanner = () => {
  const { ads_banner } = useContext(GeneralContext);
  const MySwal = withReactContent(Swal);

  useEffect(() => {
    if (!ads_banner?.length || !ads_banner[0]?.img_url) {
      return;
    }

    MySwal.fire({
      imageUrl: toMediaUrl(ads_banner[0].img_url),
      imageHeight: 150,
      width: '100%',
      text: ads_banner[0]?.text || '',
      confirmButtonText: 'OK',
      customClass: {
        popup: 'my-swal-popup',
        htmlContainer: 'my-swal-text',
      },
    });
  }, [ads_banner]);

  return null;
};

export default AdsBanner;
