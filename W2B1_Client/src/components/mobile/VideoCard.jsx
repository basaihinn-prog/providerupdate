import { forwardRef } from 'react';

const toMediaUrl = (path) => {
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) return path;
  return new URL(path, window.location.origin).toString();
};

const VideoCard = forwardRef(({ video }, ref) => {
  return (
    <div
      className='videocard'
      style={{ height: '80vh', display: 'flex', justifyContent: 'center', alignItems: 'center' }}
    >
      <video
        ref={ref}
        className='videocard__video'
        src={toMediaUrl(video)}
        loop
        style={{ width: '100%', height: '100%' }}
      />
    </div>
  );
});

export default VideoCard;
