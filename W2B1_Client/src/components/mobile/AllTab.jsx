import React from 'react';
import useFetch from '../../hooks/useFetch';
import BASE_URL from '../../hooks/baseUrl';
import Games from './Games';
import { Spinner } from 'react-bootstrap';

const AllTab = () => {
  const { data: types, loading } = useFetch(`${BASE_URL}/game_types`);

  return (
    <div className='p-3'>
      <h5>All Games</h5>
      {loading && <Spinner />}
      {types?.map((type) => (
        <div key={type.id ?? type.code}>
          <h2 className='mb-3'>{type.name}</h2>
          <Games games={type.providers} type={type.id ?? type.code} />
        </div>
      ))}
    </div>
  );
};

export default AllTab;
